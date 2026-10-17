<?php

namespace Tests\Feature\Auth;

use App\Contracts\EscortPortalClient;
use App\Contracts\SmsSender;
use App\Exceptions\SmsDispatchFailedException;
use App\Models\DeviceBinding;
use App\Models\OtpChallenge;
use App\Services\DeviceSignatureService;
use App\Services\OtpAbuseProtection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class InitiateOwnerAuthTest extends TestCase
{
    use RefreshDatabase;

    private const PHONE = '+421900123456';

    private object $smsSender;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::create(2026, 10, 17, 12, 0, 0, 'UTC'));
        config()->set('security.owner_enrollment_enabled', false);
        config()->set('security.owner_phone_number', null);
        Http::preventStrayRequests();
        Http::fake();
        $portal = Mockery::mock(EscortPortalClient::class);
        $portal->shouldNotReceive('fetchAdHtml');
        $this->app->instance(EscortPortalClient::class, $portal);
        $this->smsSender = new class implements SmsSender
        {
            public array $messages = [];

            public function sendOtp(string $phoneNumber, string $otp): void
            {
                $this->messages[] = ['phone_number' => $phoneNumber, 'otp' => $otp];
            }
        };
        $this->app->instance(SmsSender::class, $this->smsSender);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_owner_enrollment_is_disabled_by_default_even_with_a_configured_phone(): void
    {
        config()->set('security.owner_phone_number', self::PHONE);

        $this->postJson('/api/auth/owner/initiate', ['phone_number' => self::PHONE])
            ->assertForbidden()->assertJsonPath('code', 'owner_enrollment_unavailable');

        $this->assertNoIssuance();
    }

    #[DataProvider('invalidConfiguration')]
    public function test_enabled_enrollment_rejects_invalid_private_configuration(mixed $phone): void
    {
        config()->set('security.owner_enrollment_enabled', true);
        config()->set('security.owner_phone_number', $phone);

        $this->postJson('/api/auth/owner/initiate', ['phone_number' => self::PHONE])
            ->assertStatus(503)
            ->assertJsonPath('code', 'owner_enrollment_unavailable')
            ->assertJsonPath('meta.retryable', false);

        $this->assertNoIssuance();
    }

    public static function invalidConfiguration(): array
    {
        return [[null], [''], ['+421 900 123 456'], ['+0123456789'], ['not-a-number'], [['+421900123456']]];
    }

    public function test_a_different_phone_cannot_use_owner_enrollment(): void
    {
        $this->enableOwner();

        $this->postJson('/api/auth/owner/initiate', ['phone_number' => '+421900000001'])
            ->assertForbidden()
            ->assertJsonPath('code', 'owner_enrollment_unavailable')
            ->assertDontSee(self::PHONE);

        $this->assertNoIssuance();
    }

    #[DataProvider('invalidRequests')]
    public function test_owner_phone_input_requires_canonical_bounded_e164(mixed $phone): void
    {
        $this->enableOwner();

        $this->postJson('/api/auth/owner/initiate', ['phone_number' => $phone])
            ->assertUnprocessable()->assertJsonPath('code', 'validation_failed')
            ->assertJsonValidationErrors('phone_number');

        $this->assertNoIssuance();
    }

    public static function invalidRequests(): array
    {
        return [[null], [''], ['0910123456'], ['+421 900 123 456'], ['+0123456789'], ['+1234567'], ['+1234567890123456'], [[]]];
    }

    public function test_it_issues_a_hashed_challenge_without_a_portal_or_device_binding(): void
    {
        $this->enableOwner();
        $response = $this->initiate();

        $response->assertCreated()->assertJsonPath('code', 'auth.sms_initiated')
            ->assertJsonPath('data.masked_phone_number', '+421***456')
            ->assertJsonMissingPath('data.otp')->assertDontSee(self::PHONE);
        $this->assertStringContainsString('"meta":{}', $response->getContent());
        $this->assertCount(1, $this->smsSender->messages);
        $challenge = OtpChallenge::query()->sole();
        $this->assertSame('owner-enrollment', $challenge->ad_url);
        $this->assertSame(self::PHONE, $challenge->phone_number);
        $this->assertSame(self::PHONE, $this->smsSender->messages[0]['phone_number']);
        $otp = $this->smsSender->messages[0]['otp'];
        $this->assertMatchesRegularExpression('/^[0-9]{6}$/D', $otp);
        $this->assertNotSame($otp, $challenge->otp_hash);
        $this->assertTrue($challenge->hasValidOtp($otp));
        $this->assertTrue($challenge->expires_at->equalTo(now()->addMinutes(15)));
        $this->assertDatabaseCount('device_bindings', 0);
        Http::assertNothingSent();
    }

    public function test_owner_and_ad_initiation_share_ip_limits(): void
    {
        config()->set('security.initiate_per_minute', 1);
        $this->postJson('/api/auth/initiate', [])->assertUnprocessable();

        $this->enableOwner();
        $this->initiate()->assertStatus(429)->assertJsonPath('code', 'rate_limited');

        $this->assertNoIssuance();
    }

    #[DataProvider('smsBudgetLimits')]
    public function test_owner_enrollment_uses_existing_recipient_and_global_reservations(string $setting): void
    {
        $this->enableOwner();
        config()->set('security.'.$setting, 1);
        app(OtpAbuseProtection::class)->reserveSms(self::PHONE);
        Carbon::setTestNow(now()->addSeconds(61));

        $this->initiate()->assertStatus(429)->assertJsonPath('code', 'rate_limited');

        $this->assertNoIssuance();
    }

    public static function smsBudgetLimits(): array
    {
        return [
            ['sms_recipient_per_hour'], ['sms_recipient_per_day'],
            ['sms_global_per_hour'], ['sms_global_per_day'],
        ];
    }

    public function test_failed_send_keeps_the_challenge_and_does_not_refund_the_budget(): void
    {
        $this->enableOwner();
        $sender = Mockery::mock(SmsSender::class);
        $sender->shouldReceive('sendOtp')->once()->andThrow(SmsDispatchFailedException::create());
        $this->app->instance(SmsSender::class, $sender);

        $this->initiate()->assertStatus(503)->assertJsonPath('code', 'sms_dispatch_failed');
        $id = OtpChallenge::query()->sole()->challenge_id;
        $this->initiate()->assertStatus(429)->assertJsonPath('code', 'rate_limited');
        $this->assertSame($id, OtpChallenge::query()->sole()->challenge_id);
        $this->assertDatabaseCount('device_bindings', 0);
    }

    public function test_owner_enrollment_requires_a_shared_limiter_in_production(): void
    {
        $this->enableOwner();
        $this->app->detectEnvironment(fn () => 'production');
        config()->set('cache.limiter', 'array');

        $this->initiate()->assertStatus(503)->assertJsonPath('code', 'abuse_protection_unavailable');

        $this->assertNoIssuance();
    }

    public function test_owner_challenges_expire_under_the_existing_verification_rules(): void
    {
        $this->enableOwner();
        $id = $this->initiate()->assertCreated()->json('data.challenge_id');
        Carbon::setTestNow(now()->addMinutes(16));

        $this->postJson('/api/auth/verify', [
            'challenge_id' => $id, 'otp' => $this->smsSender->messages[0]['otp'],
            'public_key' => 'synthetic-key', 'signature' => 'synthetic-signature',
        ])->assertUnprocessable()->assertJsonPath('code', 'otp_invalid_or_expired');

        $this->assertDatabaseCount('device_bindings', 0);
    }

    public function test_owner_verification_cannot_bypass_the_attempt_budget(): void
    {
        $this->enableOwner();
        config()->set('security.otp_verification_attempts', 1);
        $id = $this->initiate()->assertCreated()->json('data.challenge_id');
        $wrongOtp = $this->smsSender->messages[0]['otp'] === '000000' ? '000001' : '000000';
        $payload = [
            'challenge_id' => $id, 'otp' => $wrongOtp,
            'public_key' => 'synthetic-key', 'signature' => 'synthetic-signature',
        ];
        $this->postJson('/api/auth/verify', $payload)
            ->assertUnprocessable()->assertJsonPath('code', 'otp_invalid_or_expired');
        $payload['otp'] = $this->smsSender->messages[0]['otp'];
        $this->postJson('/api/auth/verify', $payload)->assertStatus(429)->assertJsonPath('code', 'rate_limited');
        $this->assertDatabaseCount('device_bindings', 0);
    }

    public function test_resend_replaces_the_challenge_without_resetting_recipient_budgets(): void
    {
        $this->enableOwner();
        config()->set('security.sms_recipient_per_hour', 2);
        $first = $this->initiate()->assertCreated()->json('data.challenge_id');
        Carbon::setTestNow(now()->addSeconds(61));
        $second = $this->initiate()->assertCreated()->json('data.challenge_id');
        $this->assertNotSame($first, $second);
        $this->assertDatabaseCount('otp_challenges', 1);
        Carbon::setTestNow(now()->addSeconds(61));
        $this->initiate()->assertStatus(429)->assertJsonPath('code', 'rate_limited');
        $this->assertSame($second, OtpChallenge::query()->sole()->challenge_id);
        $this->assertCount(2, $this->smsSender->messages);
    }

    public function test_signed_verification_is_required_and_grants_only_normal_access(): void
    {
        $this->enableOwner();
        $id = $this->initiate()->assertCreated()->json('data.challenge_id');
        $otp = $this->smsSender->messages[0]['otp'];
        $private = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        $this->assertNotFalse($private);
        $details = openssl_pkey_get_details($private);
        $this->assertIsArray($details);
        $signatures = app(DeviceSignatureService::class);
        $public = $signatures->normalizePublicKey($details['key']);
        $verification = ['challenge_id' => $id, 'otp' => $otp, 'public_key' => $public, 'signature' => base64_encode('invalid')];

        $this->postJson('/api/auth/verify', $verification)
            ->assertUnprocessable()->assertJsonPath('code', 'signature_invalid');
        $this->assertDatabaseCount('device_bindings', 0);
        $this->assertTrue(openssl_sign($signatures->payload($id, $public), $signature, $private, OPENSSL_ALGO_SHA256));
        $verification['signature'] = base64_encode($signature);
        $this->postJson('/api/auth/verify', $verification)->assertOk()->assertJsonPath('code', 'auth.verified');
        $this->assertSame($public, DeviceBinding::query()->sole()->public_key);
        $this->assertDatabaseCount('otp_challenges', 0);
        $this->postJson('/api/auth/verify', $verification)
            ->assertUnprocessable()->assertJsonPath('code', 'challenge_not_found');

        $target = hash('sha256', '+421900000002');
        $this->postJson('/api/blacklist/check', ['target_hash' => $target])->assertUnauthorized();
        $this->assertTrue(openssl_sign($signatures->blacklistCheckPayload($target, $public), $signature, $private, OPENSSL_ALGO_SHA256));
        $this->postJson('/api/blacklist/check', [
            'target_hash' => $target, 'public_key' => $public, 'signature' => base64_encode($signature),
        ])->assertOk()->assertJsonPath('data.features', []);
        $this->assertTrue(openssl_sign($signatures->reportPayload('+421900000002', 'aggressive', $public), $signature, $private, OPENSSL_ALGO_SHA256));
        $report = ['client_phone_number' => '+421900000002', 'feature' => 'aggressive', 'public_key' => $public, 'signature' => base64_encode($signature)];
        $this->postJson('/api/reports', $report)->assertCreated()
            ->assertJsonPath('data.unique_reporter_count', 1)->assertJsonPath('data.level', 'level_1');
        $this->postJson('/api/reports', $report)->assertUnprocessable()->assertJsonPath('code', 'duplicate_report');
        Http::assertNothingSent();
    }

    private function enableOwner(): void
    {
        config()->set('security.owner_enrollment_enabled', true);
        config()->set('security.owner_phone_number', self::PHONE);
    }

    private function initiate(): TestResponse
    {
        return $this->postJson('/api/auth/owner/initiate', ['phone_number' => self::PHONE]);
    }

    private function assertNoIssuance(): void
    {
        $this->assertCount(0, $this->smsSender->messages);
        $this->assertDatabaseCount('otp_challenges', 0);
        $this->assertDatabaseCount('device_bindings', 0);
        Http::assertNothingSent();
    }
}
