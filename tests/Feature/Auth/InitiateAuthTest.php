<?php

namespace Tests\Feature\Auth;

use App\Contracts\SmsSender;
use App\Models\OtpChallenge;
use App\Services\OtpChallengeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
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
}
