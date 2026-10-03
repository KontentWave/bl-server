<?php

namespace Tests\Feature\Auth;

use App\Contracts\SmsSender;
use App\Services\AuthVerificationService;
use App\Services\DeviceSignatureService;
use App\Services\OtpChallengeService;
use App\Services\SmstoolsSmsSender;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class VerifyAuthTest extends TestCase
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

    public function test_it_binds_a_device_when_otp_signature_and_challenge_verification_succeed(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 3, 31, 12, 0, 0, 'UTC'));
        config()->set('services.escort_portal.driver', 'fixture');
        config()->set('services.escort_portal.fixture_directory', 'tests/Fixtures/escort_ads/active');

        [$challenge, $otp] = app(OtpChallengeService::class)->issue('https://portal.example.test/escort/miriam');
        [$privateKey, $publicKey] = $this->generateKeyPair();

        $response = $this->postJson('/api/auth/verify', [
            'challenge_id' => $challenge->challenge_id,
            'otp' => $otp,
            'public_key' => $publicKey,
            'signature' => $this->signPayload($privateKey, $challenge->challenge_id, $publicKey),
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('code', 'auth.verified')
            ->assertJsonPath('data.challenge_id', $challenge->challenge_id)
            ->assertJsonPath('data.masked_phone_number', '+421***456');

        $this->assertStringContainsString('"meta":{}', $response->getContent());

        $this->assertDatabaseHas('device_bindings', [
            'phone_number' => '+421900123456',
        ]);
        $this->assertDatabaseMissing('otp_challenges', [
            'phone_number' => '+421900123456',
        ]);
    }

    public function test_it_rejects_an_expired_otp_during_hardware_binding(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 3, 31, 12, 0, 0, 'UTC'));
        config()->set('services.escort_portal.driver', 'fixture');
        config()->set('services.escort_portal.fixture_directory', 'tests/Fixtures/escort_ads/active');

        [$challenge, $otp] = app(OtpChallengeService::class)->issue('https://portal.example.test/escort/miriam');
        [$privateKey, $publicKey] = $this->generateKeyPair();

        Carbon::setTestNow(now()->addMinutes(16));

        $response = $this->postJson('/api/auth/verify', [
            'challenge_id' => $challenge->challenge_id,
            'otp' => $otp,
            'public_key' => $publicKey,
            'signature' => $this->signPayload($privateKey, $challenge->challenge_id, $publicKey),
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonPath('code', 'otp_invalid_or_expired')
            ->assertJsonValidationErrors('otp');

        $this->assertDatabaseCount('device_bindings', 0);
        $this->assertDatabaseHas('otp_challenges', [
            'phone_number' => '+421900123456',
        ]);
    }

    public function test_it_rejects_an_invalid_device_signature(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 3, 31, 12, 0, 0, 'UTC'));
        config()->set('services.escort_portal.driver', 'fixture');
        config()->set('services.escort_portal.fixture_directory', 'tests/Fixtures/escort_ads/active');

        [$challenge, $otp] = app(OtpChallengeService::class)->issue('https://portal.example.test/escort/miriam');
        [, $publicKey] = $this->generateKeyPair();

        $response = $this->postJson('/api/auth/verify', [
            'challenge_id' => $challenge->challenge_id,
            'otp' => $otp,
            'public_key' => $publicKey,
            'signature' => base64_encode('not-a-valid-signature'),
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonPath('code', 'signature_invalid')
            ->assertJsonValidationErrors('signature');

        $this->assertDatabaseCount('device_bindings', 0);
        $this->assertDatabaseHas('otp_challenges', [
            'phone_number' => '+421900123456',
        ]);
    }

    public function test_it_accepts_a_valid_signature_when_the_public_key_lines_are_indented(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 3, 31, 12, 0, 0, 'UTC'));
        config()->set('services.escort_portal.driver', 'fixture');
        config()->set('services.escort_portal.fixture_directory', 'tests/Fixtures/escort_ads/active');

        [$challenge, $otp] = app(OtpChallengeService::class)->issue('https://portal.example.test/escort/miriam');
        [$privateKey, $publicKey] = $this->generateKeyPair();

        $indentedPublicKey = preg_replace('/\n/', "\n            ", trim($publicKey));

        $response = $this->postJson('/api/auth/verify', [
            'challenge_id' => $challenge->challenge_id,
            'otp' => $otp,
            'public_key' => $indentedPublicKey,
            'signature' => $this->signPayload($privateKey, $challenge->challenge_id, $publicKey),
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('code', 'auth.verified');

        $this->assertDatabaseHas('device_bindings', [
            'phone_number' => '+421900123456',
        ]);
    }

    public function test_it_rejects_unknown_or_already_used_challenges(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 3, 31, 12, 0, 0, 'UTC'));

        [$privateKey, $publicKey] = $this->generateKeyPair();
        $challengeId = (string) Str::uuid();

        $response = $this->postJson('/api/auth/verify', [
            'challenge_id' => $challengeId,
            'otp' => '123456',
            'public_key' => $publicKey,
            'signature' => $this->signPayload($privateKey, $challengeId, $publicKey),
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonPath('code', 'challenge_not_found')
            ->assertJsonValidationErrors('challenge_id');

        $this->assertDatabaseCount('device_bindings', 0);
    }

    public function test_it_rolls_back_the_binding_when_challenge_consumption_fails(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 3, 31, 12, 0, 0, 'UTC'));
        config()->set('services.escort_portal.driver', 'fixture');
        config()->set('services.escort_portal.fixture_directory', 'tests/Fixtures/escort_ads/active');

        [$challenge, $otp] = app(OtpChallengeService::class)->issue('https://portal.example.test/escort/miriam');
        [$privateKey, $publicKey] = $this->generateKeyPair();
        DB::statement('CREATE TRIGGER fail_otp_challenge_delete BEFORE DELETE ON otp_challenges BEGIN SELECT RAISE(ABORT, "simulated persistence failure"); END;');

        try {
            app(AuthVerificationService::class)->verify(
                $challenge->challenge_id,
                $otp,
                $publicKey,
                $this->signPayload($privateKey, $challenge->challenge_id, $publicKey),
            );
            $this->fail('Expected challenge consumption to fail.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('simulated persistence failure', $exception->getMessage());
        }

        $this->assertDatabaseCount('device_bindings', 0);
        $this->assertDatabaseHas('otp_challenges', ['challenge_id' => $challenge->challenge_id]);
    }

    public function test_a_resend_replaces_the_challenge_without_consuming_the_new_challenge(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 3, 31, 12, 0, 0, 'UTC'));
        config()->set('services.escort_portal.driver', 'fixture');
        config()->set('services.escort_portal.fixture_directory', 'tests/Fixtures/escort_ads/active');

        [$previousChallenge] = app(OtpChallengeService::class)->issue('https://portal.example.test/escort/miriam');
        Carbon::setTestNow(now()->addMinutes(2));
        [$currentChallenge, $otp] = app(OtpChallengeService::class)->issue('https://portal.example.test/escort/miriam');
        [$privateKey, $publicKey] = $this->generateKeyPair();

        $this->assertNotSame($previousChallenge->challenge_id, $currentChallenge->challenge_id);

        $this->postJson('/api/auth/verify', [
            'challenge_id' => $previousChallenge->challenge_id,
            'otp' => $otp,
            'public_key' => $publicKey,
            'signature' => $this->signPayload($privateKey, $previousChallenge->challenge_id, $publicKey),
        ])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'challenge_not_found');

        $this->assertDatabaseCount('device_bindings', 0);
        $this->assertDatabaseHas('otp_challenges', ['challenge_id' => $currentChallenge->challenge_id]);
    }

    public function test_a_successfully_consumed_challenge_cannot_rebind_to_a_different_key(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 3, 31, 12, 0, 0, 'UTC'));
        config()->set('services.escort_portal.driver', 'fixture');
        config()->set('services.escort_portal.fixture_directory', 'tests/Fixtures/escort_ads/active');

        [$challenge, $otp] = app(OtpChallengeService::class)->issue('https://portal.example.test/escort/miriam');
        [$firstPrivateKey, $firstPublicKey] = $this->generateKeyPair();
        [$secondPrivateKey, $secondPublicKey] = $this->generateKeyPair();

        $this->postJson('/api/auth/verify', [
            'challenge_id' => $challenge->challenge_id,
            'otp' => $otp,
            'public_key' => $firstPublicKey,
            'signature' => $this->signPayload($firstPrivateKey, $challenge->challenge_id, $firstPublicKey),
        ])->assertOk()->assertJsonPath('code', 'auth.verified');

        $this->postJson('/api/auth/verify', [
            'challenge_id' => $challenge->challenge_id,
            'otp' => $otp,
            'public_key' => $secondPublicKey,
            'signature' => $this->signPayload($secondPrivateKey, $challenge->challenge_id, $secondPublicKey),
        ])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'challenge_not_found');

        $this->assertDatabaseCount('device_bindings', 1);
        $this->assertDatabaseHas('device_bindings', [
            'phone_number' => '+421900123456',
            'public_key' => app(DeviceSignatureService::class)->normalizePublicKey($firstPublicKey),
        ]);
    }

    public function test_it_verifies_a_signed_challenge_after_smstools_accepts_the_scraped_recipient(): void
    {
        config()->set('services.escort_portal.driver', 'fixture');
        config()->set('services.escort_portal.fixture_directory', 'tests/Fixtures/escort_ads/active');
        config()->set('services.escort_portal.development_phone_override', null);
        config()->set('services.sms.driver', 'smstools');
        config()->set('services.sms.smstools.api_key', 'test-api-key');
        config()->set('services.sms.from', 'Blacklist');
        config()->set('services.sms.smstools.endpoint', 'https://api.smstools.sk/3/send_batch');
        $this->app->forgetInstance(SmsSender::class);
        $this->assertInstanceOf(SmstoolsSmsSender::class, app(SmsSender::class));

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

        [$challenge, $otp] = app(OtpChallengeService::class)->issue('https://portal.example.test/escort/miriam');
        [$privateKey, $publicKey] = $this->generateKeyPair();

        $this->postJson('/api/auth/verify', [
            'challenge_id' => $challenge->challenge_id,
            'otp' => $otp,
            'public_key' => $publicKey,
            'signature' => $this->signPayload($privateKey, $challenge->challenge_id, $publicKey),
        ])
            ->assertOk()
            ->assertJsonPath('code', 'auth.verified')
            ->assertJsonPath('data.challenge_id', $challenge->challenge_id)
            ->assertJsonPath('data.masked_phone_number', '+421***456');

        Http::assertSent(fn ($request) => $request['data']['recipients'][0]['phonenr'] === '+421900123456');
        Http::assertSentCount(1);
        $this->assertDatabaseCount('device_bindings', 1);
        $this->assertDatabaseCount('otp_challenges', 0);
    }

    public function test_challenge_attempt_limit_cannot_be_bypassed_with_a_valid_otp_or_another_ip(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 10, 2, 12, 0, 0, 'UTC'));
        config()->set('services.escort_portal.driver', 'fixture');
        config()->set('services.escort_portal.fixture_directory', 'tests/Fixtures/escort_ads/active');
        config()->set('security.otp_verification_attempts', 5);
        [$challenge, $otp] = app(OtpChallengeService::class)->issue('https://portal.example.test/escort/miriam');
        [$privateKey, $publicKey] = $this->generateKeyPair();
        $payload = [
            'challenge_id' => $challenge->challenge_id,
            'otp' => $otp === '000000' ? '111111' : '000000',
            'public_key' => $publicKey,
            'signature' => $this->signPayload($privateKey, $challenge->challenge_id, $publicKey),
        ];

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/api/auth/verify', $payload)
                ->assertUnprocessable()->assertJsonPath('code', 'otp_invalid_or_expired');
        }

        $payload['otp'] = $otp;
        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.4'])
            ->postJson('/api/auth/verify', $payload)
            ->assertStatus(429)
            ->assertJsonPath('code', 'rate_limited')
            ->assertJsonPath('meta.retry_after', 900)
            ->assertHeader('Retry-After', '900');

        $this->assertDatabaseCount('device_bindings', 0);
        $this->assertDatabaseHas('otp_challenges', ['challenge_id' => $challenge->challenge_id]);
    }

    private function generateKeyPair(): array
    {
        $privateKey = openssl_pkey_new([
            'private_key_type' => OPENSSL_KEYTYPE_EC,
            'curve_name' => 'prime256v1',
        ]);

        openssl_pkey_export($privateKey, $privateKeyPem);

        $details = openssl_pkey_get_details($privateKey);

        return [$privateKeyPem, $details['key']];
    }

    private function signPayload(string $privateKey, string $challengeId, string $publicKey): string
    {
        $payload = app(DeviceSignatureService::class)->payload($challengeId, $publicKey);

        openssl_sign($payload, $signature, $privateKey, OPENSSL_ALGO_SHA256);

        return base64_encode($signature);
    }
}
