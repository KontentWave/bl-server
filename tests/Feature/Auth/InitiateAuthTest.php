<?php

namespace Tests\Feature\Auth;

use App\Contracts\SmsSender;
use App\Models\OtpChallenge;
use App\Services\OtpChallengeService;
use App\Services\SmstoolsSmsSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
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
}
