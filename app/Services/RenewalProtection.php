<?php

namespace App\Services;

use App\Exceptions\ApiDomainException;
use Closure;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class RenewalProtection
{
    public function __construct(private readonly OtpAbuseProtection $sharedStore) {}

    public function locked(string $key, Closure $callback): mixed
    {
        $this->sharedStore->assertSharedStore();
        try {
            $store = Cache::store(config('cache.limiter') ?: config('cache.default'));
            if (! $store->getStore() instanceof LockProvider) {
                throw new \RuntimeException('Shared locks unavailable.');
            }
            $lock = $store->lock('renewal:lock:'.$key, $this->leaseSeconds());
            $acquired = $lock->get();
        } catch (ApiDomainException|ThrottleRequestsException $exception) {
            throw $exception;
        } catch (\Throwable) {
            throw new ApiDomainException('abuse_protection_unavailable', 'Authorization is temporarily unavailable.', 503,
                meta: ['retryable' => true]);
        }
        if (! $acquired) {
            $this->denied(1);
        }
        try {
            return $callback();
        } finally {
            try {
                $lock->release();
            } catch (\Throwable) {
                // Cleanup cannot reclassify an already protected, committed result; the lease stays closed.
                Log::warning('renewal.lock_release_failed');
            }
        }
    }

    public function leaseSeconds(): int
    {
        return max(120, (int) config('services.escort_portal.timeout', 10) + 30);
    }

    public function requests(string $publicKey, string $ip): void
    {
        $this->locked('requests', function () use ($publicKey, $ip): void {
            $this->reserve('key:'.hash('sha256', $publicKey), 20, 3600);
            $this->reserve('ip:'.hash('sha256', $ip), 3, 60);
        });
    }

    public function fetch(): void
    {
        $this->locked('fetch-budget', fn () => $this->reserve('app-fetch', 100, 86400));
    }

    private function reserve(string $key, int $limit, int $seconds): void
    {
        try {
            $store = Cache::store(config('cache.limiter') ?: config('cache.default'));
            $key = 'renewal:budget:'.$key;
            $window = $store->get($key);
            if (! is_array($window) || $window['until'] <= now()->timestamp) {
                $window = ['count' => 0, 'until' => now()->timestamp + $seconds];
            }
            if ($window['count'] >= $limit) {
                $this->denied($window['until'] - now()->timestamp);
            }
            $window['count']++;
            if (! $store->put($key, $window, max(1, $window['until'] - now()->timestamp))) {
                throw new \RuntimeException('Shared budget write failed.');
            }
        } catch (ThrottleRequestsException $exception) {
            throw $exception;
        } catch (\Throwable) {
            throw new ApiDomainException('abuse_protection_unavailable', 'Authorization is temporarily unavailable.', 503,
                meta: ['retryable' => true]);
        }
    }

    public function denied(int $delay): never
    {
        throw new ThrottleRequestsException('Too many requests.', headers: ['Retry-After' => max(1, $delay)]);
    }
}
