<?php

namespace App\Services;

use App\Exceptions\ApiDomainException;
use App\Models\OtpChallenge;
use Closure;
use Illuminate\Cache\RateLimiter;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Support\Facades\Cache;

class OtpAbuseProtection
{
    public function __construct(private readonly RateLimiter $rateLimiter) {}

    public function assertSharedStore(): void
    {
        $store = config('cache.limiter') ?: config('cache.default');
        $driver = config('cache.stores.'.$store.'.driver');

        if (! app()->environment(['local', 'testing']) && ! in_array($driver, ['database', 'redis'], true)) {
            throw new ApiDomainException(
                apiCode: 'abuse_protection_unavailable',
                message: 'Verification is temporarily unavailable.',
                status: 503,
                meta: ['retryable' => true],
            );
        }
    }

    public function reserveSms(string $phoneNumber): void
    {
        $this->assertSharedStore();
        $recipient = hash_hmac('sha256', $phoneNumber, (string) config('app.key'));

        $this->withLock('otp:sms:budget', function () use ($recipient): void {
            foreach ([
                ['otp:sms:cooldown:'.$recipient, 1, max(1, (int) config('security.resend_cooldown_seconds', 60))],
                ['otp:sms:recipient:hour:'.$recipient, config('security.sms_recipient_per_hour', 3), 3600],
                ['otp:sms:recipient:day:'.$recipient, config('security.sms_recipient_per_day', 5), 86400],
                ['otp:sms:global:hour', config('security.sms_global_per_hour', 20), 3600],
                ['otp:sms:global:day', config('security.sms_global_per_day', 100), 86400],
            ] as [$key, $limit, $seconds]) {
                $this->reserve($key, max(1, (int) $limit), $seconds);
            }
        });
    }

    public function recordVerificationAttempt(OtpChallenge $challenge): void
    {
        $this->assertSharedStore();
        $seconds = max(1, (int) ceil(now()->diffInSeconds($challenge->expires_at, false)));

        $key = 'otp:verify:'.hash('sha256', $challenge->challenge_id);

        $this->withLock($key.':lock', fn () => $this->reserve(
            $key,
            max(1, (int) config('security.otp_verification_attempts', 5)),
            $seconds,
        ));
    }

    private function withLock(string $key, Closure $callback): void
    {
        $store = config('cache.limiter') ?: config('cache.default');
        $cacheStore = Cache::store($store)->getStore();

        if (! $cacheStore instanceof LockProvider) {
            throw new ApiDomainException(
                apiCode: 'abuse_protection_unavailable',
                message: 'Verification is temporarily unavailable.',
                status: 503,
                meta: ['retryable' => true],
            );
        }

        if ($cacheStore->lock($key, 10)->get($callback) === false) {
            throw new ThrottleRequestsException('Too many requests.', headers: ['Retry-After' => 1]);
        }
    }

    private function reserve(string $key, int $limit, int $seconds): void
    {
        if ($this->rateLimiter->hit($key, $seconds) > $limit) {
            throw new ThrottleRequestsException('Too many requests.', headers: [
                'Retry-After' => max(1, $this->rateLimiter->availableIn($key)),
            ]);
        }
    }
}
