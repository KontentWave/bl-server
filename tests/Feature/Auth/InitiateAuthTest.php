<?php

namespace Tests\Feature\Auth;

use App\Contracts\SmsSender;
use App\Exceptions\SmsDispatchFailedException;
use App\Models\OtpChallenge;
use App\Services\OtpAbuseProtection;
use App\Services\OtpChallengeService;
use App\Services\SmstoolsSmsSender;
use Illuminate\Cache\RateLimiter;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class InitiateAuthTest extends TestCase
{
    use RefreshDatabase;

    private object $fakeSmsSender;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fakeSmsSender = new class implements SmsSender
        {
            public array $messages = [];

            public function sendOtp(string $phoneNumber, string $otp): void
            {
                $this->messages[] = [
                    'phone_number' => $phoneNumber,
                    'otp' => $otp,
                ];
            }
        };

        $this->app->instance(SmsSender::class, $this->fakeSmsSender);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_it_generates_an_otp_and_stores_only_a_hash(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 3, 31, 12, 0, 0, 'UTC'));
        config()->set('services.escort_portal.driver', 'fixture');
        config()->set('services.escort_portal.fixture_directory', 'tests/Fixtures/escort_ads/active');

        $response = $this->postJson('/api/auth/initiate', [
            'ad_url' => 'https://portal.example.test/escort/miriam',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('code', 'auth.sms_initiated')
            ->assertJsonPath('data.masked_phone_number', '+421***456');

        $this->assertStringContainsString('"meta":{}', $response->getContent());

        $this->assertCount(1, $this->fakeSmsSender->messages);

        $plainTextOtp = $this->fakeSmsSender->messages[0]['otp'];
        $otpChallenge = OtpChallenge::query()->firstOrFail();

        $this->assertSame('https://portal.example.test/escort/miriam', $otpChallenge->ad_url);
        $this->assertNotSame($plainTextOtp, $otpChallenge->otp_hash);
        $this->assertTrue($otpChallenge->hasValidOtp($plainTextOtp));
        $this->assertTrue($otpChallenge->expires_at->equalTo(now()->addMinutes(15)));
        $this->assertNotEmpty($otpChallenge->challenge_id);
    }

    public function test_otp_remains_valid_at_fifteen_minutes_and_expires_afterwards(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 3, 31, 12, 0, 0, 'UTC'));
        config()->set('services.escort_portal.driver', 'fixture');
        config()->set('services.escort_portal.fixture_directory', 'tests/Fixtures/escort_ads/active');

        [$otpChallenge, $plainTextOtp] = app(OtpChallengeService::class)->issue('https://portal.example.test/escort/miriam');

        Carbon::setTestNow(now()->addMinutes(15));

        $this->assertTrue($otpChallenge->fresh()->hasValidOtp($plainTextOtp));

        Carbon::setTestNow(now()->addSecond());

        $this->assertFalse($otpChallenge->fresh()->hasValidOtp($plainTextOtp));
    }

    public function test_requesting_a_new_challenge_invalidates_the_previous_one_for_the_same_scraped_phone(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 3, 31, 12, 0, 0, 'UTC'));
        config()->set('services.escort_portal.driver', 'fixture');
        config()->set('services.escort_portal.fixture_directory', 'tests/Fixtures/escort_ads/active');

        $firstResponse = $this->postJson('/api/auth/initiate', [
            'ad_url' => 'https://portal.example.test/escort/miriam',
        ]);

        $firstResponse
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('code', 'auth.sms_initiated');

        $this->assertStringContainsString('"meta":{}', $firstResponse->getContent());

        $firstOtp = $this->fakeSmsSender->messages[0]['otp'];
        $firstChallengeId = $firstResponse->json('data.challenge_id');

        Carbon::setTestNow(now()->addMinutes(5));

        $secondResponse = $this->postJson('/api/auth/initiate', [
            'ad_url' => 'https://portal.example.test/escort/miriam',
        ]);

        $secondResponse
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('code', 'auth.sms_initiated');

        $this->assertStringContainsString('"meta":{}', $secondResponse->getContent());

        $secondOtp = $this->fakeSmsSender->messages[1]['otp'];
        $secondChallengeId = $secondResponse->json('data.challenge_id');
        $otpChallenge = OtpChallenge::query()->where('phone_number', '+421900123456')->firstOrFail();

        $this->assertSame(1, OtpChallenge::query()->count());
        $this->assertNotSame($firstChallengeId, $secondChallengeId);
        $this->assertFalse($otpChallenge->hasValidOtp($firstOtp));
        $this->assertTrue($otpChallenge->hasValidOtp($secondOtp));
    }

    public function test_it_rejects_invalid_or_unsupported_ad_urls(): void
    {
        $response = $this->postJson('/api/auth/initiate', [
            'ad_url' => 'not-a-url',
        ]);

        $response
            ->assertStatus(400)
            ->assertJsonPath('success', false)
            ->assertJsonPath('code', 'invalid_ad_url');

        $this->assertCount(0, $this->fakeSmsSender->messages);
    }

    public function test_it_fails_cleanly_when_no_phone_can_be_extracted_from_the_ad(): void
    {
        config()->set('services.escort_portal.driver', 'fixture');
        config()->set('services.escort_portal.fixture_directory', 'tests/Fixtures/escort_ads/suspended');

        $response = $this->postJson('/api/auth/initiate', [
            'ad_url' => 'https://portal.example.test/escort/miriam',
        ]);

        $response
            ->assertStatus(400)
            ->assertJsonPath('success', false)
            ->assertJsonPath('code', 'phone_extraction_failed');

        $this->assertCount(0, $this->fakeSmsSender->messages);
    }

    public function test_it_returns_a_distinct_error_when_an_amaterky_ad_is_temporarily_disabled(): void
    {
        config()->set('services.escort_portal.driver', 'fixture');
        config()->set('services.escort_portal.fixture_directory', 'tests/Fixtures/escort_ads/temporarily_disabled');

        $response = $this->postJson('/api/auth/initiate', [
            'ad_url' => 'https://amaterky.sk/32297',
        ]);

        $response
            ->assertStatus(400)
            ->assertJsonPath('success', false)
            ->assertJsonPath('code', 'ad_temporarily_disabled')
            ->assertJsonPath('meta.retryable', false)
            ->assertJsonPath('meta.ad_state', 'temporarily_disabled');

        $this->assertCount(0, $this->fakeSmsSender->messages);
    }

    public function test_smstools_acceptance_returns_the_existing_initiation_envelope(): void
    {
        config()->set('services.escort_portal.driver', 'fixture');
        config()->set('services.escort_portal.fixture_directory', 'tests/Fixtures/escort_ads/active');
        config()->set('services.escort_portal.development_phone_override', null);
        config()->set('services.sms.driver', 'smstools');
        config()->set('services.sms.smstools.api_key', 'test-api-key');
        config()->set('services.sms.from', 'Blacklist');
        config()->set('services.sms.smstools.endpoint', 'https://api.smstools.sk/3/send_batch');
        $this->app->forgetInstance(SmsSender::class);
        Http::preventStrayRequests();
        Http::fake(fn () => Http::response([
            'id' => 'OK',
            'data' => [
                'batch_id' => 12345,
                'recipients' => ['accepted' => [[
                    'phonenr' => '+421900123456',
                    'msg_id' => 22345,
                ]]],
            ],
        ], 200));

        $response = $this->postJson('/api/auth/initiate', [
            'ad_url' => 'https://portal.example.test/escort/miriam',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('code', 'auth.sms_initiated')
            ->assertJsonPath('data.masked_phone_number', '+421***456')
            ->assertJsonMissingPath('data.otp');

        $this->assertStringContainsString('"meta":{}', $response->getContent());
        $this->assertSame(OtpChallenge::query()->firstOrFail()->challenge_id, $response->json('data.challenge_id'));
        Http::assertSent(fn ($request) => $request['data']['recipients'][0]['phonenr'] === '+421900123456');
        Http::assertSentCount(1);
    }

    #[DataProvider('smsFailures')]
    public function test_smstools_failures_preserve_the_api_envelope_and_challenge_lifecycle(string $failure): void
    {
        config()->set('services.escort_portal.driver', 'fixture');
        config()->set('services.escort_portal.fixture_directory', 'tests/Fixtures/escort_ads/active');
        config()->set('services.escort_portal.development_phone_override', null);

        [$previousChallenge] = app(OtpChallengeService::class)->issue('https://portal.example.test/escort/miriam');
        Carbon::setTestNow(now()->addSeconds(60));

        config()->set('services.sms.driver', 'smstools');
        config()->set('services.sms.smstools.api_key', 'test-api-key');
        config()->set('services.sms.from', 'Blacklist');
        config()->set('services.sms.smstools.endpoint', 'https://api.smstools.sk/3/send_batch');
        $this->app->forgetInstance(SmsSender::class);
        $this->assertInstanceOf(SmstoolsSmsSender::class, app(SmsSender::class));

        Http::preventStrayRequests();
        $attempts = 0;

        Http::fake(function () use ($failure, &$attempts) {
            $attempts++;

            if (in_array($failure, ['timeout', 'connection'], true)) {
                throw new ConnectionException('Sensitive transport failure');
            }

            return match ($failure) {
                'rejection' => Http::response(['id' => 'NEDOSTATOK_KREDITU', 'note' => 'Sensitive rejection details'], 200),
                'malformed' => Http::response('not-json', 200),
                'partial' => Http::response(['id' => 'OK'], 200),
            };
        });

        $response = $this->postJson('/api/auth/initiate', [
            'ad_url' => 'https://portal.example.test/escort/miriam',
        ]);

        $response
            ->assertStatus(503)
            ->assertJsonPath('success', false)
            ->assertJsonPath('code', 'sms_dispatch_failed')
            ->assertJsonPath('meta.retryable', true)
            ->assertJsonMissingPath('data.challenge_id');

        $response->assertJsonPath('errors', []);
        $this->assertStringNotContainsString('Sensitive', $response->getContent());
        $this->assertSame(1, $attempts);
        $this->assertSame(1, OtpChallenge::query()->count());
        $currentChallenge = OtpChallenge::query()->firstOrFail();
        $this->assertNotSame($previousChallenge->challenge_id, $currentChallenge->challenge_id);
        $this->assertSame('+421900123456', $currentChallenge->phone_number);
        $this->assertTrue($currentChallenge->expires_at->greaterThan(now()));
    }

    public static function smsFailures(): array
    {
        return [
            'provider rejection' => ['rejection'],
            'timeout' => ['timeout'],
            'connection failure' => ['connection'],
            'malformed response' => ['malformed'],
            'partial response' => ['partial'],
        ];
    }

    public function test_resend_cooldown_preserves_the_challenge_and_cannot_be_bypassed_with_another_ip(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 10, 2, 12, 0, 0, 'UTC'));
        config()->set('services.escort_portal.driver', 'fixture');
        config()->set('services.escort_portal.fixture_directory', 'tests/Fixtures/escort_ads/active');
        $payload = ['ad_url' => 'https://portal.example.test/escort/miriam'];

        $first = $this->postJson('/api/auth/initiate', $payload)->assertCreated();
        $challengeId = $first->json('data.challenge_id');

        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.2'])
            ->postJson('/api/auth/initiate', $payload)
            ->assertStatus(429)
            ->assertJsonPath('code', 'rate_limited')
            ->assertJsonPath('meta.retryable', true)
            ->assertJsonPath('meta.retry_after', 60)
            ->assertHeader('Retry-After', '60');

        $this->assertCount(1, $this->fakeSmsSender->messages);
        $this->assertSame($challengeId, OtpChallenge::query()->firstOrFail()->challenge_id);

        Carbon::setTestNow(now()->addSeconds(60));
        $second = $this->postJson('/api/auth/initiate', $payload)->assertCreated();
        $this->assertNotSame($challengeId, $second->json('data.challenge_id'));
        $this->assertCount(2, $this->fakeSmsSender->messages);
    }

    #[DataProvider('smsBudgetLimits')]
    public function test_sms_attempt_budgets_block_before_replacing_the_challenge(string $setting): void
    {
        Carbon::setTestNow(Carbon::create(2026, 10, 2, 12, 0, 0, 'UTC'));
        config()->set('services.escort_portal.driver', 'fixture');
        config()->set('services.escort_portal.fixture_directory', 'tests/Fixtures/escort_ads/active');
        config()->set('security.'.$setting, 2);
        $payload = ['ad_url' => 'https://portal.example.test/escort/miriam'];

        $this->postJson('/api/auth/initiate', $payload)->assertCreated();
        Carbon::setTestNow(now()->addSeconds(61));
        $lastAllowed = $this->postJson('/api/auth/initiate', $payload)->assertCreated();
        Carbon::setTestNow(now()->addSeconds(61));

        $this->postJson('/api/auth/initiate', $payload)
            ->assertStatus(429)
            ->assertJsonPath('code', 'rate_limited')
            ->assertJsonMissingPath('data.challenge_id');

        $this->assertCount(2, $this->fakeSmsSender->messages);
        $this->assertSame($lastAllowed->json('data.challenge_id'), OtpChallenge::query()->firstOrFail()->challenge_id);
    }

    public static function smsBudgetLimits(): array
    {
        return [
            ['sms_recipient_per_hour'], ['sms_recipient_per_day'],
            ['sms_global_per_hour'], ['sms_global_per_day'],
        ];
    }

    public function test_the_global_sms_budget_is_shared_across_different_recipients(): void
    {
        config()->set('security.sms_global_per_hour', 1);
        app(OtpAbuseProtection::class)->reserveSms('+421900123456');

        $this->expectException(ThrottleRequestsException::class);

        app(OtpAbuseProtection::class)->reserveSms('+421900000001');
    }

    public function test_a_failed_sms_send_does_not_refund_the_reservation(): void
    {
        config()->set('services.escort_portal.driver', 'fixture');
        config()->set('services.escort_portal.fixture_directory', 'tests/Fixtures/escort_ads/active');
        $sender = Mockery::mock(SmsSender::class);
        $sender->shouldReceive('sendOtp')->once()->andThrow(SmsDispatchFailedException::create());
        $this->app->instance(SmsSender::class, $sender);
        $payload = ['ad_url' => 'https://portal.example.test/escort/miriam'];

        $this->postJson('/api/auth/initiate', $payload)
            ->assertStatus(503)->assertJsonPath('code', 'sms_dispatch_failed');
        $challengeId = OtpChallenge::query()->firstOrFail()->challenge_id;

        $this->postJson('/api/auth/initiate', $payload)
            ->assertStatus(429)->assertJsonPath('code', 'rate_limited');
        $this->assertSame($challengeId, OtpChallenge::query()->firstOrFail()->challenge_id);
    }

    #[DataProvider('apiRoutes')]
    public function test_all_api_routes_are_limited_before_validation(string $route): void
    {
        config()->set('security.api_per_minute', 1);

        $this->postJson($route, [])->assertUnprocessable();
        $response = $this->withHeaders(['X-Forwarded-For' => '198.51.100.3'])->postJson($route, []);
        $response->assertStatus(429)
            ->assertJsonPath('success', false)
            ->assertJsonPath('code', 'rate_limited')
            ->assertJsonPath('meta.retryable', true)
            ->assertJsonMissingPath('data.challenge_id');
        $this->assertGreaterThan(0, $response->json('meta.retry_after'));
        $this->assertCount(0, $this->fakeSmsSender->messages);
    }

    public static function apiRoutes(): array
    {
        return [['/api/auth/initiate'], ['/api/auth/verify'], ['/api/reports'], ['/api/blacklist/check']];
    }

    #[DataProvider('authRouteLimits')]
    public function test_auth_routes_have_additional_ip_limits(string $setting, string $route): void
    {
        config()->set('security.api_per_minute', 100);
        config()->set('security.'.$setting, 1);

        $this->postJson($route, [])->assertUnprocessable();
        $this->postJson($route, [])->assertStatus(429)->assertJsonPath('code', 'rate_limited');
        $this->assertCount(0, $this->fakeSmsSender->messages);
    }

    public static function authRouteLimits(): array
    {
        return [
            ['initiate_per_minute', '/api/auth/initiate'],
            ['initiate_per_hour', '/api/auth/initiate'],
            ['verify_per_minute', '/api/auth/verify'],
        ];
    }

    public function test_non_shared_limiter_stores_fail_closed_outside_local_and_testing(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        config()->set('cache.limiter', 'array');

        $this->postJson('/api/auth/initiate', [])
            ->assertStatus(503)
            ->assertJsonPath('code', 'abuse_protection_unavailable');
        $this->assertCount(0, $this->fakeSmsSender->messages);
        $this->assertDatabaseCount('otp_challenges', 0);
    }

    public function test_unsafe_hosts_are_rejected_before_scraping_or_sending(): void
    {
        Http::preventStrayRequests();
        Http::fake();
        config()->set('services.escort_portal.driver', 'http');

        $this->postJson('/api/auth/initiate', ['ad_url' => 'https://127.0.0.1/ad'])
            ->assertStatus(400)->assertJsonPath('code', 'invalid_ad_url');
        Http::assertNothingSent();
        $this->assertCount(0, $this->fakeSmsSender->messages);
        $this->assertDatabaseCount('otp_challenges', 0);
    }

    public function test_a_busy_sms_budget_lock_blocks_before_challenge_replacement_or_sending(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 10, 2, 12, 0, 0, 'UTC'));
        config()->set('services.escort_portal.driver', 'fixture');
        config()->set('services.escort_portal.fixture_directory', 'tests/Fixtures/escort_ads/active');
        $payload = ['ad_url' => 'https://portal.example.test/escort/miriam'];
        $first = $this->postJson('/api/auth/initiate', $payload)->assertCreated();
        Carbon::setTestNow(now()->addSeconds(61));
        $cacheStore = Cache::store(config('cache.limiter'))->getStore();

        if (! $cacheStore instanceof LockProvider) {
            $this->fail('The test cache must support locks.');
        }

        $lock = $cacheStore->lock('otp:sms:budget', 10);
        $this->assertTrue($lock->get());

        try {
            $this->postJson('/api/auth/initiate', $payload)
                ->assertStatus(429)->assertJsonPath('meta.retry_after', 1);
            $this->assertCount(1, $this->fakeSmsSender->messages);
            $this->assertSame($first->json('data.challenge_id'), OtpChallenge::query()->firstOrFail()->challenge_id);
        } finally {
            $lock->release();
        }
    }

    #[DataProvider('oversizedInputs')]
    public function test_api_inputs_are_bounded_before_parsing(string $route, string $field, int $length): void
    {
        $this->postJson($route, [$field => str_repeat('x', $length)])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'validation_failed')
            ->assertJsonValidationErrors($field);

        $this->assertCount(0, $this->fakeSmsSender->messages);
    }

    public static function oversizedInputs(): array
    {
        return [
            ['/api/auth/initiate', 'ad_url', 2049],
            ['/api/auth/verify', 'public_key', 8193],
            ['/api/auth/verify', 'signature', 4097],
            ['/api/reports', 'public_key', 8193],
            ['/api/reports', 'signature', 4097],
            ['/api/blacklist/check', 'public_key', 8193],
            ['/api/blacklist/check', 'signature', 4097],
        ];
    }

    public function test_independent_limiter_instances_share_database_reservations(): void
    {
        config()->set('cache.limiter', 'database');
        config()->set('cache.stores.database.connection', 'sqlite');
        config()->set('cache.stores.database.lock_connection', 'sqlite');
        $first = new OtpAbuseProtection(new RateLimiter(Cache::store('database')));
        $second = new OtpAbuseProtection(new RateLimiter(Cache::store('database')));

        $first->reserveSms('+421900123456');
        $this->expectException(ThrottleRequestsException::class);
        $second->reserveSms('+421900123456');
    }
}
