<?php

namespace Tests\Feature\Auth;

use App\Contracts\EscortAdVerifier;
use App\Exceptions\EscortPortalTimeoutException;
use App\Exceptions\EscortPortalUnavailableException;
use App\Services\AuthChallengeService;
use App\Services\DeviceSignatureService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class VerifyAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_it_binds_a_device_when_password_signature_and_ad_verification_succeed(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 3, 31, 12, 0, 0, 'UTC'));
        config()->set('services.escort_portal.driver', 'fixture');
        config()->set('services.escort_portal.fixture_directory', 'tests/Fixtures/escort_ads/active');

        [, $password] = app(AuthChallengeService::class)->issue('+421900123456');
        [$privateKey, $publicKey] = $this->generateKeyPair();

        $response = $this->postJson('/api/auth/verify', [
            'phone_number' => '+421900123456',
            'password' => $password,
            'public_key' => $publicKey,
            'signature' => $this->signPayload($privateKey, '+421900123456', $publicKey),
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('code', 'auth.verified')
            ->assertJsonPath('data.phone_number', '+421900123456');

        $this->assertDatabaseHas('device_bindings', [
            'phone_number' => '+421900123456',
        ]);
        $this->assertDatabaseMissing('auth_challenges', [
            'phone_number' => '+421900123456',
        ]);
    }

    public function test_it_rejects_an_expired_password_during_hardware_binding(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 3, 31, 12, 0, 0, 'UTC'));
        config()->set('services.escort_portal.driver', 'fixture');
        config()->set('services.escort_portal.fixture_directory', 'tests/Fixtures/escort_ads/active');

        [, $password] = app(AuthChallengeService::class)->issue('+421900123456');
        [$privateKey, $publicKey] = $this->generateKeyPair();

        Carbon::setTestNow(now()->addMinutes(61));

        $response = $this->postJson('/api/auth/verify', [
            'phone_number' => '+421900123456',
            'password' => $password,
            'public_key' => $publicKey,
            'signature' => $this->signPayload($privateKey, '+421900123456', $publicKey),
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonPath('code', 'validation_failed')
            ->assertJsonValidationErrors('password');

        $this->assertDatabaseCount('device_bindings', 0);
        $this->assertDatabaseHas('auth_challenges', [
            'phone_number' => '+421900123456',
        ]);
    }

    public function test_it_rejects_an_invalid_device_signature(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 3, 31, 12, 0, 0, 'UTC'));
        config()->set('services.escort_portal.driver', 'fixture');
        config()->set('services.escort_portal.fixture_directory', 'tests/Fixtures/escort_ads/active');

        [, $password] = app(AuthChallengeService::class)->issue('+421900123456');
        [, $publicKey] = $this->generateKeyPair();

        $response = $this->postJson('/api/auth/verify', [
            'phone_number' => '+421900123456',
            'password' => $password,
            'public_key' => $publicKey,
            'signature' => base64_encode('not-a-valid-signature'),
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonPath('code', 'validation_failed')
            ->assertJsonValidationErrors('signature');

        $this->assertDatabaseCount('device_bindings', 0);
        $this->assertDatabaseHas('auth_challenges', [
            'phone_number' => '+421900123456',
        ]);
    }

    public function test_it_rejects_hardware_binding_when_no_active_ad_can_be_verified(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 3, 31, 12, 0, 0, 'UTC'));
        config()->set('services.escort_portal.driver', 'fixture');
        config()->set('services.escort_portal.fixture_directory', 'tests/Fixtures/escort_ads/suspended');

        [, $password] = app(AuthChallengeService::class)->issue('+421900123456');
        [$privateKey, $publicKey] = $this->generateKeyPair();

        $response = $this->postJson('/api/auth/verify', [
            'phone_number' => '+421900123456',
            'password' => $password,
            'public_key' => $publicKey,
            'signature' => $this->signPayload($privateKey, '+421900123456', $publicKey),
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonPath('code', 'escort_ad_not_verified')
            ->assertJsonValidationErrors('phone_number');

        $this->assertDatabaseCount('device_bindings', 0);
        $this->assertDatabaseHas('auth_challenges', [
            'phone_number' => '+421900123456',
        ]);
    }

    public function test_it_returns_a_retryable_timeout_error_when_portal_verification_times_out(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 3, 31, 12, 0, 0, 'UTC'));

        [, $password] = app(AuthChallengeService::class)->issue('+421900123456');
        [$privateKey, $publicKey] = $this->generateKeyPair();

        $this->app->bind(EscortAdVerifier::class, fn () => new class implements EscortAdVerifier
        {
            public function hasActiveAdForPhoneNumber(string $phoneNumber): bool
            {
                throw EscortPortalTimeoutException::forPhoneNumber($phoneNumber);
            }
        });

        $response = $this->postJson('/api/auth/verify', [
            'phone_number' => '+421900123456',
            'password' => $password,
            'public_key' => $publicKey,
            'signature' => $this->signPayload($privateKey, '+421900123456', $publicKey),
        ]);

        $response
            ->assertStatus(503)
            ->assertJsonPath('success', false)
            ->assertJsonPath('code', 'escort_portal_timeout')
            ->assertJsonPath('meta.retryable', true);
    }

    public function test_it_returns_a_retryable_unavailable_error_when_portal_verification_fails_upstream(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 3, 31, 12, 0, 0, 'UTC'));

        [, $password] = app(AuthChallengeService::class)->issue('+421900123456');
        [$privateKey, $publicKey] = $this->generateKeyPair();

        $this->app->bind(EscortAdVerifier::class, fn () => new class implements EscortAdVerifier
        {
            public function hasActiveAdForPhoneNumber(string $phoneNumber): bool
            {
                throw EscortPortalUnavailableException::forPhoneNumber($phoneNumber, 502);
            }
        });

        $response = $this->postJson('/api/auth/verify', [
            'phone_number' => '+421900123456',
            'password' => $password,
            'public_key' => $publicKey,
            'signature' => $this->signPayload($privateKey, '+421900123456', $publicKey),
        ]);

        $response
            ->assertStatus(503)
            ->assertJsonPath('success', false)
            ->assertJsonPath('code', 'escort_portal_unavailable')
            ->assertJsonPath('meta.retryable', true)
            ->assertJsonPath('meta.upstream_status', 502);
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

    private function signPayload(string $privateKey, string $phoneNumber, string $publicKey): string
    {
        $payload = app(DeviceSignatureService::class)->payload($phoneNumber, $publicKey);

        openssl_sign($payload, $signature, $privateKey, OPENSSL_ALGO_SHA256);

        return base64_encode($signature);
    }
}
