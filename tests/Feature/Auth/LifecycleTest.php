<?php

namespace Tests\Feature\Auth;

use App\Contracts\EscortPortalClient;
use App\Exceptions\ApiDomainException;
use App\Exceptions\EscortPortalTimeoutException;
use App\Models\Client;
use App\Models\DeviceBinding;
use App\Models\OtpChallenge;
use App\Models\Report;
use App\Services\AuthVerificationService;
use App\Services\BindingLifecycle;
use App\Services\BlacklistQueryService;
use App\Services\DeviceSignatureService;
use App\Services\RenewalProtection;
use App\Services\ReportSubmissionService;
use Illuminate\Cache\ArrayLock;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Tests\TestCase;

class LifecycleTest extends TestCase
{
    use RefreshDatabase;

    private mixed $privateKey;

    private string $publicKey;

    private object $portal;

    private int $requestNumber = 0;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-11-03T12:00:00+00:00'));
        Http::preventStrayRequests();
        config()->set('security.api_per_minute', 10000);
        $this->privateKey = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
        $this->publicKey = app(DeviceSignatureService::class)->normalizePublicKey(openssl_pkey_get_details($this->privateKey)['key']);
        $this->portal = new class implements EscortPortalClient
        {
            public int $calls = 0;

            public ?string $html = '<a class="detail-floater-link-phone" href="tel:+421900123456">contact</a>';

            public mixed $duringFetch = null;

            public ?\Throwable $failure = null;

            public function fetchAdHtml(string $adUrl): ?string
            {
                $this->calls++;
                if ($this->duringFetch) {
                    ($this->duringFetch)();
                }
                if ($this->failure) {
                    throw $this->failure;
                }

                return $this->html;
            }
        };
        $this->app->instance(EscortPortalClient::class, $this->portal);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function enroll(?string $ad = 'https://amaterky.sk/fixture', string $phone = '+421900123456'): DeviceBinding
    {
        $challenge = OtpChallenge::create([
            'phone_number' => $phone, 'ad_url' => $ad ?? 'owner-enrollment',
            'otp_hash' => Hash::make('123456'), 'expires_at' => now()->addMinutes(15),
        ]);
        openssl_sign(app(DeviceSignatureService::class)->payload($challenge->challenge_id, $this->publicKey), $signature, $this->privateKey, OPENSSL_ALGO_SHA256);

        return app(AuthVerificationService::class)->verify($challenge->challenge_id, '123456', $this->publicKey, base64_encode($signature));
    }

    private function sendStatus(?string $id = null)
    {
        $data = ['request_id' => $id ?? (string) Str::uuid(), 'public_key' => $this->publicKey];

        return $this->sendSigned('/api/auth/status', ['operation' => 'auth.status'] + $data, $data);
    }

    private function renewalData(DeviceBinding $binding, ?string $id = null): array
    {
        return [
            'enrollment_id' => $binding->enrollment_id,
            'request_id' => $id ?? (string) Str::uuid(),
            'next_renewal_at' => $binding->next_renewal_at?->toIso8601String(),
            'public_key' => $this->publicKey,
        ];
    }

    private function renew(array $data)
    {
        return $this->sendSigned('/api/auth/renew', ['operation' => 'auth.renew'] + $data, $data);
    }

    private function sendSigned(string $path, array $canonical, array $data)
    {
        openssl_sign(json_encode($canonical, JSON_THROW_ON_ERROR), $signature, $this->privateKey, OPENSSL_ALGO_SHA256);
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.'.(++$this->requestNumber)]);

        return $this->postJson($path, $data + ['signature' => base64_encode($signature)]);
    }

    public function test_status_bootstraps_legacy_without_fetch_and_preserves_ambiguity(): void
    {
        DeviceBinding::create(['phone_number' => '+421900123456', 'public_key' => $this->publicKey, 'verified_at' => now()]);
        $this->sendStatus()->assertOk()->assertJsonPath('code', 'auth.status')->assertJsonPath('data.lifecycle.kind', 'legacy');
        $this->assertSame(0, $this->portal->calls);
    }

    public function test_manual_enrollment_metadata_due_boundary_and_success_replay(): void
    {
        $binding = $this->enroll();
        $verified = $binding->verified_at->toIso8601String();
        $data = $this->renewalData($binding);
        $this->assertNotNull($data['enrollment_id']);
        Carbon::setTestNow(now()->addSeconds(86399));
        $this->renew($data)->assertOk()->assertJsonPath('code', 'auth.renewal_not_due');
        $this->assertSame(0, $this->portal->calls);
        Carbon::setTestNow(now()->addSecond());
        $data['request_id'] = (string) Str::uuid();
        $response = $this->renew($data)->assertOk()->assertJsonPath('code', 'auth.renewed');
        $this->assertSame(now()->addDay()->toIso8601String(), $response->json('data.lifecycle.next_renewal_at'));
        $this->assertSame($verified, $response->json('data.lifecycle.verified_at'));
        $this->renew($data)->assertOk()->assertJsonPath('code', 'auth.renewed');
        $this->assertSame(1, $this->portal->calls);
        $data['request_id'] = (string) Str::uuid();
        $this->renew($data)->assertStatus(409)->assertJsonPath('code', 'auth.renewal_cycle_changed');
    }

    public function test_unknown_failure_duplicate_delay_and_fresh_retry(): void
    {
        $binding = $this->enroll();
        Carbon::setTestNow(now()->addDay());
        $data = $this->renewalData($binding);
        $this->portal->html = null;
        $this->renew($data)->assertStatus(503)->assertJsonPath('code', 'auth.ad_status_unavailable')->assertHeader('Retry-After', '900');
        Carbon::setTestNow(now()->addSeconds(10));
        $this->renew($data)->assertStatus(503)->assertHeader('Retry-After', '890');
        $data['request_id'] = (string) Str::uuid();
        $this->renew($data)->assertStatus(429);
        $this->assertSame(1, $this->portal->calls);
        Carbon::setTestNow(now()->addSeconds(890));
        $this->renew($data)->assertStatus(503)->assertHeader('Retry-After', '3600');
        $this->assertSame(2, $this->portal->calls);
        $this->assertNull($binding->fresh()->last_renewed_at);
    }

    public function test_offboarding_terminal_and_explicit_same_key_new_generation(): void
    {
        $binding = $this->enroll();
        Carbon::setTestNow(now()->addDay());
        $data = $this->renewalData($binding);
        $this->portal->html = '<h2 class="alert-heading">Vypnutý zadávateľom</h2>';
        $this->renew($data)->assertForbidden()->assertJsonPath('code', 'device_offboarded')->assertJsonPath('meta.lifecycle.reason', 'ad_disabled');
        $this->portal->html = '<a class="detail-floater-link-phone" href="tel:+421900123456">contact</a>';
        $this->renew($data)->assertForbidden();
        $this->sendStatus()->assertForbidden();
        $new = $this->enroll();
        $this->assertNotSame($binding->enrollment_id, $new->enrollment_id);
        $this->renew($data)->assertStatus(409)->assertJsonPath('code', 'auth.enrollment_changed');
        $this->sendStatus()->assertOk()->assertJsonPath('data.lifecycle.state', 'active');
        $this->assertSame(1, $this->portal->calls);
    }

    public function test_contact_order_and_expiry_selector_are_narrow(): void
    {
        $binding = $this->enroll();
        Carbon::setTestNow(now()->addDay());
        $this->portal->html = '<a class="detail-floater-link-phone" href="tel:+421900654321">first</a><a class="detail-floater-link-sms" href="sms:+421900123456">original</a>';
        $this->renew($this->renewalData($binding))->assertOk()->assertJsonPath('code', 'auth.renewed');
        Carbon::setTestNow(now()->addDay());
        $this->portal->html = '<p>Vypršala platnosť</p>';
        $this->renew($this->renewalData($binding->fresh()))->assertStatus(503);
        Carbon::setTestNow(now()->addMinutes(15));
        $this->portal->html = '<h2 class="alert-heading">Vypršala platnosť</h2>';
        $this->renew($this->renewalData($binding->fresh()))->assertForbidden()->assertJsonPath('meta.lifecycle.reason', 'ad_expired');
    }

    public function test_reentrant_duplicate_is_locked_and_new_generation_discards_fetch(): void
    {
        $binding = $this->enroll();
        Carbon::setTestNow(now()->addDay());
        $data = $this->renewalData($binding);
        $this->portal->duringFetch = function () use ($data): void {
            $this->renew($data)->assertStatus(429)->assertHeader('Retry-After', '1');
            $this->enroll(null, '+421900654321');
        };
        $this->renew($data)->assertStatus(409)->assertJsonPath('code', 'auth.enrollment_changed');
        $this->assertSame(1, $this->portal->calls);
        $this->assertSame('superseded', $binding->fresh()->lifecycle_state);
        $this->sendStatus()->assertOk()->assertJsonPath('data.lifecycle.kind', 'phone_only');
    }

    public function test_phone_only_and_legacy_exempt_even_when_issuance_disabled(): void
    {
        $binding = $this->enroll(null);
        config()->set('security.owner_enrollment_enabled', false);
        config()->set('security.owner_phone_numbers', '');
        Carbon::setTestNow(now()->addYears(10));
        $this->renew($this->renewalData($binding))->assertOk()->assertJsonPath('code', 'auth.renewal_exempt');
        $binding->update(['enrollment_kind' => 'legacy']);
        $this->renew($this->renewalData($binding))->assertOk()->assertJsonPath('code', 'auth.renewal_exempt');
        $this->assertSame(0, $this->portal->calls);
        $this->assertNull($binding->fresh()->last_renewed_at);
    }

    public function test_ambiguous_legacy_first_binding_query_report_then_manual_selection(): void
    {
        $first = DeviceBinding::create(['phone_number' => '+421900123456', 'public_key' => $this->publicKey, 'verified_at' => now()]);
        DeviceBinding::create(['phone_number' => '+421900654321', 'public_key' => $this->publicKey, 'verified_at' => now()]);
        $this->sendStatus()->assertStatus(409)->assertJsonPath('code', 'auth.enrollment_ambiguous')->assertJsonMissingPath('meta.lifecycle');
        $target = hash('sha256', '+421900111111');
        openssl_sign(app(DeviceSignatureService::class)->blacklistCheckPayload($target, $this->publicKey), $signature, $this->privateKey, OPENSSL_ALGO_SHA256);
        $this->assertSame([], app(BlacklistQueryService::class)->check($target, $this->publicKey, base64_encode($signature))['features']);
        openssl_sign(app(DeviceSignatureService::class)->reportPayload('+421900111111', 'aggressive', $this->publicKey), $signature, $this->privateKey, OPENSSL_ALGO_SHA256);
        $result = app(ReportSubmissionService::class)->submit('+421900111111', 'aggressive', $this->publicKey, base64_encode($signature));
        $this->assertSame(hash('sha256', $first->phone_number), $result['reporter_hash']);
        $selected = $this->enroll(null, '+421900654321');
        $this->assertSame('superseded', $first->fresh()->lifecycle_state);
        $this->sendStatus()->assertOk()->assertJsonPath('data.lifecycle.enrollment_id', $selected->enrollment_id);
        $this->assertDatabaseCount('device_bindings', 2);
        $this->assertDatabaseCount('reports', 1);
    }

    public function test_signed_offboarded_query_report_snapshot_and_invalid_signature_codes(): void
    {
        $binding = $this->enroll();
        $binding->update(['lifecycle_state' => 'offboarded', 'offboarded_at' => now(), 'offboarding_reason' => 'ad_disabled', 'next_renewal_at' => null]);
        $target = hash('sha256', '+421900111111');
        openssl_sign(app(DeviceSignatureService::class)->blacklistCheckPayload($target, $this->publicKey), $signature, $this->privateKey, OPENSSL_ALGO_SHA256);
        $this->postJson('/api/blacklist/check', ['target_hash' => $target, 'public_key' => $this->publicKey, 'signature' => base64_encode($signature)])
            ->assertForbidden()->assertJsonPath('code', 'device_offboarded')->assertJsonPath('meta.lifecycle.enrollment_id', $binding->enrollment_id)->assertJsonMissingPath('meta.request_id');
        $this->postJson('/api/blacklist/check', ['target_hash' => $target, 'public_key' => $this->publicKey, 'signature' => base64_encode('invalid')])
            ->assertUnauthorized()->assertJsonPath('code', 'blacklist_query_unauthorized')->assertJsonMissingPath('meta.lifecycle');
        openssl_sign(app(DeviceSignatureService::class)->reportPayload('+421900111111', 'aggressive', $this->publicKey), $signature, $this->privateKey, OPENSSL_ALGO_SHA256);
        $this->postJson('/api/reports', ['client_phone_number' => '+421900111111', 'feature' => 'aggressive', 'public_key' => $this->publicKey, 'signature' => base64_encode($signature)])
            ->assertForbidden()->assertJsonPath('code', 'device_offboarded')->assertJsonMissingPath('meta.request_id');
        $this->postJson('/api/reports', ['client_phone_number' => '+421900111111', 'feature' => 'aggressive', 'public_key' => $this->publicKey, 'signature' => base64_encode('invalid')])
            ->assertUnprocessable()->assertJsonPath('code', 'signature_invalid');
        $this->assertDatabaseCount('reports', 0);
        $this->assertSame(0, $this->portal->calls);
    }

    public function test_contact_change_preserves_existing_community_rows_and_unknown_ambiguity(): void
    {
        $binding = $this->enroll();
        $client = Client::create(['client_hash' => hash('sha256', 'fixture-target')]);
        Report::create(['client_id' => $client->id, 'reporter_hash' => hash('sha256', $binding->phone_number), 'feature' => 'aggressive']);
        Carbon::setTestNow(now()->addDay());
        $this->portal->html = '<a class="detail-floater-link-phone" href="tel:+421900654321">new</a><a class="detail-floater-link-sms" href="sms:unknown">ambiguous</a>';
        $this->renew($this->renewalData($binding))->assertStatus(503);
        Carbon::setTestNow(now()->addMinutes(15));
        $this->portal->html = '<a class="detail-floater-link-phone" href="tel:+421900654321">new</a>';
        $this->renew($this->renewalData($binding))->assertForbidden()->assertJsonPath('meta.lifecycle.reason', 'contact_changed');
        $this->assertDatabaseCount('reports', 1);
        $this->assertDatabaseCount('clients', 1);
        $this->assertSame('+421900123456', $binding->fresh()->phone_number);
    }

    public function test_incomplete_supported_contact_sets_do_not_offboard(): void
    {
        $binding = $this->enroll();
        Carbon::setTestNow(now()->addDay());
        $this->portal->html = '<a class="detail-floater-link-phone" href="tel:+421900654321">new</a><a class="detail-floater-link-sms">unavailable</a>';
        $this->renew($this->renewalData($binding))->assertStatus(503);
        $this->assertSame('active', $binding->fresh()->lifecycle_state);

        $binding = $this->enroll('https://www.eurogirlsescort.com/fixture');
        Carbon::setTestNow(now()->addDay());
        $this->portal->html = '<div id="js-phone"><div class="js-phone-item"><i class="icon-phone" data-phone="+421900654321"></i></div><div class="js-phone-item"><span class="opacity-horizontal">+421 900</span><a class="js-phone">Show phone</a></div></div>';
        $this->renew($this->renewalData($binding))->assertStatus(503);
        $this->assertSame('active', $binding->fresh()->lifecycle_state);
        $this->assertNull($binding->fresh()->last_renewed_at);
        Carbon::setTestNow(now()->addMinutes(15));
        $this->portal->html = '<div id="js-phone"><div class="js-phone-item"><i class="icon-phone" data-phone="+421900654321"></i><a class="js-phone">Show phone</a></div></div>';
        $this->renew($this->renewalData($binding))->assertStatus(503);
        $this->assertSame('active', $binding->fresh()->lifecycle_state);
        $this->assertSame(3, $this->portal->calls);
    }

    public function test_eurogirls_complete_contacts_renew_then_confirm_contact_change(): void
    {
        $binding = $this->enroll('https://www.eurogirlsescort.com/fixture');
        Carbon::setTestNow(now()->addDay());
        $this->portal->html = '<div id="js-phone"><div class="js-phone-item"><i class="icon-phone" data-phone="+421900654321"></i></div><div class="js-phone-item"><a class="js-phone" data-phone="AXCDL--DCbX_l">Show phone</a></div></div>';
        $this->renew($this->renewalData($binding))->assertOk()->assertJsonPath('code', 'auth.renewed');
        Carbon::setTestNow(now()->addDay());
        $this->portal->html = '<div id="js-phone"><div class="js-phone-item"><i class="icon-phone" data-phone="+421900654321"></i></div></div>';
        $this->renew($this->renewalData($binding->fresh()))->assertForbidden()
            ->assertJsonPath('meta.lifecycle.reason', 'contact_changed');
        $this->assertSame(2, $this->portal->calls);
    }

    public function test_unexpected_inspector_failure_is_unknown_with_privacy_safe_diagnostics(): void
    {
        $binding = $this->enroll();
        Carbon::setTestNow(now()->addDay());
        $this->portal->failure = new \TypeError('Private URL, recipient and signing material must not be logged.');
        Log::spy();

        $this->renew($this->renewalData($binding))->assertStatus(503)
            ->assertJsonPath('code', 'auth.ad_status_unavailable')->assertHeader('Retry-After', '900');
        Log::shouldHaveReceived('error')->with('renewal.inspection_failed', [
            'exception_class' => \TypeError::class,
        ])->once();
        $this->assertSame('active', $binding->fresh()->lifecycle_state);
        $this->assertNull($binding->fresh()->last_renewed_at);
        $this->assertSame(1, $this->portal->calls);
    }

    public function test_overdue_catch_up_uses_one_fetch_and_commit_time(): void
    {
        $binding = $this->enroll();
        $verified = $binding->verified_at->toIso8601String();
        Carbon::setTestNow(now()->addDays(7));
        $this->portal->duringFetch = function (): void {
            Carbon::setTestNow(now()->addSeconds(5));
        };
        $response = $this->renew($this->renewalData($binding))->assertOk()->assertJsonPath('code', 'auth.renewed');
        $this->assertSame($verified, $response->json('data.lifecycle.verified_at'));
        $this->assertSame(now()->toIso8601String(), $response->json('data.lifecycle.last_renewed_at'));
        $this->assertSame(now()->addSeconds(86400)->toIso8601String(), $response->json('data.lifecycle.next_renewal_at'));
        $this->assertSame(1, $this->portal->calls);
    }

    public function test_fixed_enrollment_fetch_window_and_failure_ladder_survive_retries(): void
    {
        $binding = $this->enroll();
        Carbon::setTestNow(now()->addDay());
        $start = now()->copy();
        $this->portal->html = '<html>unknown</html>';
        foreach ([900, 3600, 21600, 21600] as $delay) {
            $this->renew($this->renewalData($binding))->assertStatus(503)->assertHeader('Retry-After', (string) $delay);
            Carbon::setTestNow(now()->addSeconds($delay));
        }
        $this->renew($this->renewalData($binding))->assertStatus(429)->assertHeader('Retry-After', (string) ($start->timestamp + 86400 - now()->timestamp));
        $this->assertSame(4, $this->portal->calls);
        $this->assertDatabaseCount('renewal_receipts', 4);
        Carbon::setTestNow($start->addDay());
        $this->portal->html = '<a class="detail-floater-link-phone" href="tel:+421900123456">original</a>';
        $this->renew($this->renewalData($binding))->assertOk();
        $this->assertSame(1, $binding->fresh()->fetch_count);
        $this->assertSame(0, $binding->fresh()->renewal_failures);
    }

    public function test_failure_honors_long_retry_after_and_challenge_is_unknown(): void
    {
        $binding = $this->enroll();
        Carbon::setTestNow(now()->addDay());
        $this->portal->failure = new EscortPortalTimeoutException('escort_portal_timeout', 'Fixture only.', 503, meta: ['retry_after' => 7200]);
        $this->renew($this->renewalData($binding))->assertStatus(503)->assertHeader('Retry-After', '7200');
        Carbon::setTestNow(now()->addHours(2));
        $this->portal->failure = null;
        $this->portal->html = '<form id="challenge-form"></form><h2 class="alert-heading">Vypnutý zadávateľom</h2>';
        $this->renew($this->renewalData($binding))->assertStatus(503);
        $this->assertSame('active', $binding->fresh()->lifecycle_state);
    }

    public function test_receipt_conflict_pruning_and_terminal_precedence(): void
    {
        $binding = $this->enroll();
        $data = $this->renewalData($binding);
        $this->renew($data)->assertOk();
        $changed = $data;
        $changed['next_renewal_at'] = null;
        $this->renew($changed)->assertStatus(409)->assertJsonPath('code', 'auth.request_id_conflict');
        Carbon::setTestNow(now()->addDay());
        $success = $this->renewalData($binding);
        $this->renew($success)->assertOk()->assertJsonPath('code', 'auth.renewed');
        Carbon::setTestNow(now()->addDay());
        $this->portal->html = '<h2 class="alert-heading">Vypnuty zadavatelom</h2>';
        $this->renew($this->renewalData($binding->fresh()))->assertForbidden();
        $this->renew($success)->assertForbidden()->assertJsonPath('meta.lifecycle.state', 'offboarded');
    }

    public function test_eight_receipt_bound_and_old_cycle_replay_after_48_hours(): void
    {
        $binding = $this->enroll();
        $old = $this->renewalData($binding);
        Carbon::setTestNow(now()->addDay());
        $this->renew($old)->assertOk();
        for ($i = 0; $i < 9; $i++) {
            $this->renew($this->renewalData($binding->fresh()))->assertOk();
        }
        $this->assertDatabaseCount('renewal_receipts', 8);
        $this->renew($old)->assertStatus(409)->assertJsonPath('code', 'auth.renewal_cycle_changed');
        Carbon::setTestNow(now()->addHours(48));
        $this->renew($old)->assertStatus(409);
        $this->assertDatabaseCount('renewal_receipts', 0);
        $this->assertSame(1, $this->portal->calls);
    }

    public function test_request_key_cap_is_shared_by_status_and_renew_and_not_reset_by_enrollment(): void
    {
        $binding = $this->enroll(null);
        for ($i = 0; $i < 20; $i++) {
            ($i % 2 === 0 ? $this->sendStatus() : $this->renew($this->renewalData($binding)))->assertOk();
        }
        $this->enroll(null);
        $this->sendStatus()->assertStatus(429)->assertHeader('Retry-After', '3600');
        $this->assertSame(0, $this->portal->calls);
        Carbon::setTestNow(now()->addHour());
        $this->sendStatus()->assertOk();
    }

    public function test_ip_cap_and_application_fixed_fetch_budget(): void
    {
        $this->enroll(null);
        $this->requestNumber = 0;
        $this->sendStatus()->assertOk();
        $this->requestNumber = 0;
        $this->sendStatus()->assertOk();
        $this->requestNumber = 0;
        $this->sendStatus()->assertOk();
        $this->requestNumber = 0;
        $this->sendStatus()->assertStatus(429)->assertHeader('Retry-After', '60');
        for ($i = 0; $i < 100; $i++) {
            app(RenewalProtection::class)->fetch();
        }
        try {
            app(RenewalProtection::class)->fetch();
            $this->fail('Expected application fetch cap.');
        } catch (ThrottleRequestsException $exception) {
            $this->assertSame(86400, $exception->getHeaders()['Retry-After']);
        }
        Carbon::setTestNow(now()->addDay());
        app(RenewalProtection::class)->fetch();
        $this->assertSame(1, Cache::get('renewal:budget:app-fetch')['count']);
    }

    public function test_wire_validation_and_authorization_never_fetch(): void
    {
        $binding = $this->enroll();
        $this->postJson('/api/auth/status', [])->assertUnprocessable()->assertJsonValidationErrors(['request_id', 'public_key', 'signature']);
        $data = $this->renewalData($binding);
        $this->postJson('/api/auth/renew', $data + ['signature' => str_repeat('x', 4097)])->assertUnprocessable()->assertJsonValidationErrors('signature');
        $data['next_renewal_at'] = str_repeat('x', 65);
        $this->renew($data)->assertUnprocessable()->assertJsonValidationErrors('next_renewal_at');
        $this->postJson('/api/auth/status', ['request_id' => (string) Str::uuid(), 'public_key' => $this->publicKey, 'signature' => base64_encode('invalid')])
            ->assertUnauthorized()->assertJsonPath('code', 'auth.renewal_unauthorized')->assertJsonPath('meta.retryable', false);
        $this->assertSame(0, $this->portal->calls);
    }

    public function test_shared_store_failure_and_lock_contention_fail_closed(): void
    {
        $binding = $this->enroll();
        Carbon::setTestNow(now()->addDay());
        $lock = Cache::lock('renewal:lock:enrollment:'.$binding->enrollment_id, 120);
        $this->assertTrue($lock->get());
        $this->renew($this->renewalData($binding))->assertStatus(429)->assertHeader('Retry-After', '1');
        $lock->release();
        config()->set('cache.limiter', 'file');
        $this->app->detectEnvironment(fn () => 'production');
        $this->sendStatus()->assertStatus(503)->assertJsonPath('code', 'abuse_protection_unavailable');
        $this->assertSame(0, $this->portal->calls);
    }

    public function test_canonical_golden_strings_null_due_and_ec_signature_domain_separation(): void
    {
        $signatures = app(DeviceSignatureService::class);
        $status = ['request_id' => '11111111-1111-4111-8111-111111111111', 'public_key' => " fixture/key\r\n "];
        $this->assertSame('{"operation":"auth.status","request_id":"11111111-1111-4111-8111-111111111111","public_key":"fixture\/key"}',
            $signatures->lifecyclePayload('auth.status', $status));
        $renewal = [
            'enrollment_id' => '22222222-2222-4222-8222-222222222222',
            'request_id' => $status['request_id'], 'next_renewal_at' => null, 'public_key' => $status['public_key'],
        ];
        $this->assertSame('{"operation":"auth.renew","enrollment_id":"22222222-2222-4222-8222-222222222222","request_id":"11111111-1111-4111-8111-111111111111","next_renewal_at":null,"public_key":"fixture\/key"}',
            $signatures->lifecyclePayload('auth.renew', $renewal));
        $renewal['next_renewal_at'] = '2026-11-04T12:00:00+00:00';
        $this->assertSame('{"operation":"auth.renew","enrollment_id":"22222222-2222-4222-8222-222222222222","request_id":"11111111-1111-4111-8111-111111111111","next_renewal_at":"2026-11-04T12:00:00+00:00","public_key":"fixture\/key"}',
            $signatures->lifecyclePayload('auth.renew', $renewal));
        $this->assertSame('{"operation":"auth.status","request_id":"11111111-1111-4111-8111-111111111111","public_key":"-----BEGIN PUBLIC KEY-----\nfixture\/key\n-----END PUBLIC KEY-----"}',
            $signatures->lifecyclePayload('auth.status', [
                'request_id' => $status['request_id'],
                'public_key' => "  -----BEGIN PUBLIC KEY-----\r\n fixture/key\r\n -----END PUBLIC KEY-----  ",
            ]));
        $renewal['next_renewal_at'] = null;
        $renewal['public_key'] = $this->publicKey;
        openssl_sign($signatures->lifecyclePayload('auth.renew', $renewal), $signature, $this->privateKey, OPENSSL_ALGO_SHA256);
        $renewal['signature'] = base64_encode($signature);
        $this->assertTrue($signatures->verifyLifecycle('auth.renew', $renewal));
        $this->assertFalse($signatures->verifyLifecycle('auth.status', $renewal));
        $renewal['next_renewal_at'] = '2026-11-04T12:00:00+00:00';
        $this->assertFalse($signatures->verifyLifecycle('auth.renew', $renewal));
    }

    public function test_query_authorization_never_awaits_held_renewal_lock_even_when_overdue(): void
    {
        $binding = $this->enroll();
        Carbon::setTestNow(now()->addYears(5));
        $lock = Cache::lock('renewal:lock:enrollment:'.$binding->enrollment_id, 120);
        $this->assertTrue($lock->get());
        try {
            $target = hash('sha256', 'fixture-target');
            openssl_sign(app(DeviceSignatureService::class)->blacklistCheckPayload($target, $this->publicKey), $signature, $this->privateKey, OPENSSL_ALGO_SHA256);
            $this->assertSame([], app(BlacklistQueryService::class)->check($target, $this->publicKey, base64_encode($signature))['features']);
        } finally {
            $lock->release();
        }
        $this->assertSame(0, $this->portal->calls);
        $this->assertNull($binding->fresh()->last_renewed_at);
    }

    public function test_no_portal_fetch_inside_database_transaction_and_receipt_duplicates_return_current_snapshot(): void
    {
        $binding = $this->enroll();
        Carbon::setTestNow(now()->addDay());
        $data = $this->renewalData($binding);
        // RefreshDatabase owns the outer test transaction; renewal must not add one around I/O.
        $baselineLevel = DB::transactionLevel();
        $this->portal->duringFetch = function () use ($baselineLevel): void {
            $this->assertSame($baselineLevel, DB::transactionLevel());
        };
        $this->renew($data)->assertOk()->assertJsonPath('data.lifecycle.version', 2);
        Carbon::setTestNow(now()->addDay());
        $this->renew($this->renewalData($binding->fresh()))->assertOk()->assertJsonPath('data.lifecycle.version', 3);
        $this->renew($data)->assertOk()->assertJsonPath('code', 'auth.renewed')->assertJsonPath('data.lifecycle.version', 3);
        $this->assertSame(2, $this->portal->calls);
        $this->assertSame(1, $binding->fresh()->fetch_count);
    }

    public function test_success_resets_failure_ladder_without_refunding_fetch_reservations(): void
    {
        $binding = $this->enroll();
        Carbon::setTestNow(now()->addDay());
        $this->portal->html = null;
        $this->renew($this->renewalData($binding))->assertStatus(503);
        Carbon::setTestNow(now()->addMinutes(15));
        $this->portal->html = '<a class="detail-floater-link-phone" href="tel:+421900123456">original</a>';
        $this->renew($this->renewalData($binding))->assertOk();
        $this->assertSame(2, $binding->fresh()->fetch_count);
        $this->assertSame(0, $binding->fresh()->renewal_failures);
        $this->assertNull($binding->fresh()->retry_at);
        $this->assertSame(2, $this->portal->calls);
    }

    public function test_unknown_and_foreign_enrollment_are_unauthorized_not_offboarding(): void
    {
        $binding = $this->enroll();
        $data = $this->renewalData($binding);
        $data['enrollment_id'] = (string) Str::uuid();
        $this->renew($data)->assertUnauthorized()->assertJsonPath('code', 'auth.renewal_unauthorized')->assertJsonMissingPath('meta.lifecycle');
        DB::table('device_enrollment_generations')->insert(['enrollment_id' => $data['enrollment_id'], 'key_hash' => hash('sha256', 'other-fixture-key')]);
        $this->renew($data)->assertUnauthorized()->assertJsonMissingPath('meta.lifecycle');
        $this->assertSame(0, $this->portal->calls);
    }

    public function test_query_authorized_before_offboard_commit_may_complete_but_next_query_denies(): void
    {
        $binding = $this->enroll();
        $committed = false;
        DB::listen(function ($query) use ($binding, &$committed): void {
            if (! $committed && str_contains($query->sql, 'from "clients"')) {
                $committed = true;
                app(BindingLifecycle::class)->withSelection($this->publicKey, function ($selected) use ($binding): void {
                    $this->assertSame($binding->id, $selected->id);
                    $selected->fill(['lifecycle_state' => 'offboarded', 'lifecycle_version' => 2,
                        'offboarding_reason' => 'ad_disabled', 'offboarded_at' => now(), 'next_renewal_at' => null])->save();
                });
            }
        });
        $target = hash('sha256', 'fixture-target');
        openssl_sign(app(DeviceSignatureService::class)->blacklistCheckPayload($target, $this->publicKey), $signature, $this->privateKey, OPENSSL_ALGO_SHA256);
        $this->assertSame([], app(BlacklistQueryService::class)->check($target, $this->publicKey, base64_encode($signature))['features']);
        $this->assertTrue($committed);
        try {
            app(BlacklistQueryService::class)->check($target, $this->publicKey, base64_encode($signature));
            $this->fail('Expected terminal authorization denial after offboard commit.');
        } catch (ApiDomainException $exception) {
            $this->assertSame('device_offboarded', $exception->apiCode());
        }
        $this->assertSame(0, $this->portal->calls);
    }

    public function test_nullable_upgrade_and_rollback_preserve_legacy_binding_and_community_data(): void
    {
        $migration = require database_path('migrations/2026_11_03_210000_add_binding_lifecycle.php');
        $client = Client::create(['client_hash' => hash('sha256', 'fixture-target')]);
        Report::create(['client_id' => $client->id, 'reporter_hash' => hash('sha256', 'fixture-reporter'), 'feature' => 'aggressive']);
        $migration->down();
        DB::table('device_bindings')->insert([
            'phone_number' => '+421900123456', 'public_key' => $this->publicKey,
            'verified_at' => '2026-11-01 12:00:00', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $migration->up();
        $binding = DeviceBinding::firstOrFail();
        $this->assertNull($binding->enrollment_id);
        $this->assertNull($binding->enrollment_kind);
        $this->assertNull($binding->ad_url);
        $this->assertNull($binding->lifecycle_state);
        $this->assertSame('2026-11-01T12:00:00+00:00', $binding->verified_at->toIso8601String());
        $this->assertDatabaseCount('reports', 1);
        $this->assertDatabaseCount('clients', 1);
        $this->sendStatus()->assertOk()->assertJsonPath('data.lifecycle.kind', 'legacy')
            ->assertJsonPath('data.lifecycle.verified_at', '2026-11-01T12:00:00+00:00');
        $this->assertSame(0, $this->portal->calls);
    }

    public function test_lock_cleanup_failure_does_not_misreport_committed_renewal_as_unavailable(): void
    {
        $binding = $this->enroll();
        Carbon::setTestNow(now()->addDay());
        $store = new class extends ArrayStore
        {
            public string $target;

            public ?ArrayLock $terminalLock = null;

            public function lock($name, $seconds = 0, $owner = null)
            {
                if ($name !== $this->target) {
                    return parent::lock($name, $seconds, $owner);
                }

                return $this->terminalLock = new class($this, $name, $seconds, $owner) extends ArrayLock
                {
                    public function release()
                    {
                        throw new \RuntimeException('Fixture cleanup failure.');
                    }
                };
            }
        };
        $store->target = 'renewal:lock:enrollment:'.$binding->enrollment_id;
        Cache::shouldReceive('store')->andReturn(new Repository($store));
        try {
            $this->renew($this->renewalData($binding))->assertOk()->assertJsonPath('code', 'auth.renewed');
            $this->assertTrue($store->terminalLock->isOwnedByCurrentProcess());
            $this->assertSame(2, $binding->fresh()->lifecycle_version);
            $this->assertSame(1, $this->portal->calls);
        } finally {
            $store->terminalLock?->forceRelease();
        }
    }

    public function test_cache_lease_expiry_does_not_fetch_again_for_same_accepted_request_id(): void
    {
        $binding = $this->enroll();
        Carbon::setTestNow(now()->addDay());
        $data = $this->renewalData($binding);
        $duplicate = null;
        $this->portal->duringFetch = function () use ($data, &$duplicate): void {
            $this->portal->duringFetch = null;
            Carbon::setTestNow(now()->addSeconds(121));
            $duplicate = $this->renew($data);
        };
        $response = $this->renew($data);
        $this->assertSame(1, $this->portal->calls);
        $duplicate->assertStatus(503)->assertJsonPath('code', 'auth.ad_status_unavailable');
        $response->assertStatus(503)->assertJsonPath('code', 'auth.ad_status_unavailable');
        $this->assertNull($binding->fresh()->last_renewed_at);
        $this->assertDatabaseCount('renewal_receipts', 1);
    }

    public function test_durable_pending_receipt_blocks_different_id_after_cache_lock_loss(): void
    {
        $binding = $this->enroll();
        Carbon::setTestNow(now()->addDay());
        $data = $this->renewalData($binding);
        $this->portal->duringFetch = function () use ($binding): void {
            $this->assertSame('pending', DB::table('renewal_receipts')->value('outcome'));
            Cache::lock('renewal:lock:enrollment:'.$binding->enrollment_id, 120)->forceRelease();
            $this->renew($this->renewalData($binding))->assertStatus(429)->assertHeader('Retry-After', '1');
            $this->assertSame(1, $this->portal->calls);
        };
        $this->renew($data)->assertOk()->assertJsonPath('code', 'auth.renewed');
        $this->assertSame('auth.renewed', DB::table('renewal_receipts')->value('outcome'));
        $this->assertDatabaseCount('renewal_receipts', 1);
    }

    public function test_expired_pending_fences_old_commit_and_backoffs_different_cycle_request_ids(): void
    {
        $binding = $this->enroll();
        Carbon::setTestNow(now()->addDay());
        $data = $this->renewalData($binding);
        $fresh = $this->renewalData($binding);
        $this->portal->duringFetch = function () use ($fresh): void {
            $this->portal->duringFetch = null;
            Carbon::setTestNow(now()->addSeconds(121));
            $this->renew($fresh)->assertStatus(429)->assertHeader('Retry-After', '900');
            $this->assertSame(1, $this->portal->calls);
            $this->assertDatabaseCount('renewal_receipts', 1);
            Carbon::setTestNow(now()->addSeconds(900));
            $this->renew($fresh)->assertOk()->assertJsonPath('code', 'auth.renewed');
        };
        $this->renew($data)->assertStatus(409)->assertJsonPath('code', 'auth.renewal_cycle_changed');
        $this->assertSame(2, $this->portal->calls);
        $this->assertSame(2, $binding->fresh()->lifecycle_version);
        $this->assertSame(2, $binding->fresh()->fetch_count);
        $this->assertSame('auth.ad_status_unavailable', DB::table('renewal_receipts')->where('request_id', $data['request_id'])->value('outcome'));
    }

    public function test_early_accepted_id_stays_not_due_at_due_boundary_and_conflicts_on_changed_inputs(): void
    {
        $binding = $this->enroll();
        $data = $this->renewalData($binding);
        $this->renew($data)->assertOk()->assertJsonPath('code', 'auth.renewal_not_due');
        $this->assertDatabaseCount('renewal_receipts', 1);
        Carbon::setTestNow(now()->addDay());
        $this->renew($data)->assertOk()->assertJsonPath('code', 'auth.renewal_not_due');
        $this->assertSame(0, $this->portal->calls);
        $changed = $data;
        $changed['next_renewal_at'] = null;
        $this->renew($changed)->assertStatus(409)->assertJsonPath('code', 'auth.request_id_conflict');
        $this->assertSame(0, $this->portal->calls);
        $data['request_id'] = (string) Str::uuid();
        $this->renew($data)->assertOk()->assertJsonPath('code', 'auth.renewed');
        $this->assertSame(1, $this->portal->calls);
    }

    public function test_pending_deadline_uses_configured_timeout_plus_margin_not_just_minimum_lease(): void
    {
        config()->set('services.escort_portal.timeout', 200);
        $this->assertSame(230, app(RenewalProtection::class)->leaseSeconds());
        $binding = $this->enroll();
        Carbon::setTestNow(now()->addDay());
        $this->portal->duringFetch = function (): void {
            $this->assertSame(now()->addSeconds(230)->toDateTimeString(), DB::table('renewal_receipts')->value('retry_at'));
            Carbon::setTestNow(now()->addSeconds(200));
        };
        $this->renew($this->renewalData($binding))->assertOk();
        $this->assertSame(1, $this->portal->calls);
    }
}
