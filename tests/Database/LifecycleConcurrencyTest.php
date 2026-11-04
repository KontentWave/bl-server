<?php

namespace Tests\Database;

use App\Models\Client;
use App\Models\DeviceBinding;
use App\Models\OtpChallenge;
use App\Models\Report;
use App\Services\AuthVerificationService;
use App\Services\BindingLifecycle;
use App\Services\DeviceSignatureService;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\Support\AssertsMariaDbLockWait;
use Tests\Support\MariaDbWorker;
use Tests\TestCase;

class LifecycleConcurrencyTest extends TestCase
{
    use AssertsMariaDbLockWait;

    private array $workers = [];

    private static int $identity = 0;

    private mixed $privateKey;

    private string $publicKey;

    private DeviceBinding $binding;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-11-10T12:00:00Z')->addDays(++self::$identity));
        $this->assertSame('mariadb', DB::getDefaultConnection());
        $this->assertSame('beta_otp_test', DB::connection()->getDatabaseName());
        $this->assertMatchesRegularExpression('/^11\.4\./', DB::selectOne('SELECT VERSION() AS version')->version);
        $this->assertSame(getenv('BETA_TEST_ISOLATION'), DB::selectOne('SELECT @@tx_isolation AS isolation_level')->isolation_level);
        $this->assertSame('log', config('services.sms.driver'));
        $this->assertFalse(config('services.sms.log_otp_in_non_production'));
        Http::preventStrayRequests();
        $migrator = app(Migrator::class);
        if (! $migrator->repositoryExists()) {
            $this->assertSame([], DB::select('SHOW TABLES'));
            $migrator->getRepository()->createRepository();
        }
        $migrator->run([database_path('migrations')]);
        config()->set('database.connections.lock_observer', array_replace(
            config('database.connections.mariadb'), ['username' => 'root'],
        ));
        $this->privateKey = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
        $this->publicKey = app(DeviceSignatureService::class)->normalizePublicKey(openssl_pkey_get_details($this->privateKey)['key']);
        $phone = '+421908'.str_pad((string) self::$identity, 6, '0', STR_PAD_LEFT);
        $this->binding = app(AuthVerificationService::class)->verify(...$this->verification($phone));
        Carbon::setTestNow(now()->addDay());
    }

    protected function tearDown(): void
    {
        foreach ($this->workers as $worker) {
            $worker->stop();
        }
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function verification(string $phone): array
    {
        $challenge = OtpChallenge::create([
            'phone_number' => $phone, 'ad_url' => 'https://amaterky.sk/fixture',
            'otp_hash' => Hash::make('123456'), 'expires_at' => now()->addMinutes(15),
        ]);
        openssl_sign(app(DeviceSignatureService::class)->payload($challenge->challenge_id, $this->publicKey), $signature, $this->privateKey, OPENSSL_ALGO_SHA256);

        return [$challenge->challenge_id, '123456', $this->publicKey, base64_encode($signature)];
    }

    private function lifecycleData(string $operation = 'renew'): array
    {
        $data = ['request_id' => (string) Str::uuid(), 'public_key' => $this->publicKey];
        if ($operation === 'renew') {
            $data += ['enrollment_id' => $this->binding->enrollment_id,
                'next_renewal_at' => $this->binding->next_renewal_at->toIso8601String()];
        }
        openssl_sign(app(DeviceSignatureService::class)->lifecyclePayload('auth.'.$operation, $data), $signature, $this->privateKey, OPENSSL_ALGO_SHA256);

        return $data + ['signature' => base64_encode($signature)];
    }

    private function queryData(): array
    {
        $target = hash('sha256', 'fixture-lifecycle-target');
        openssl_sign(app(DeviceSignatureService::class)->blacklistCheckPayload($target, $this->publicKey), $signature, $this->privateKey, OPENSSL_ALGO_SHA256);

        return [$target, $this->publicKey, base64_encode($signature)];
    }

    private function worker(string $operation, array $data, ?string $pause = null, ?string $html = null): MariaDbWorker
    {
        $worker = new MariaDbWorker($operation, $data, false, now()->toIso8601String(), $pause,
            $html ?? '<a class="detail-floater-link-phone" href="tel:'.$this->binding->phone_number.'">contact</a>');
        $this->workers[] = $worker;

        return $worker;
    }

    private function finish(MariaDbWorker $worker, bool $started = true): array
    {
        if ($started) {
            $this->assertSame('started', $worker->read()['event']);
        }
        $result = $worker->read();
        $this->assertSame('finished', $result['event'], json_encode($result));
        $this->assertSame(0, $worker->wait());

        return $result;
    }

    private function assertFetching(MariaDbWorker $worker): void
    {
        $this->assertSame(['event' => 'fetching', 'transaction_level' => 0], $worker->read());
    }

    public function test_overlap_pending_claim_cache_loss_and_query_do_not_wait_for_portal(): void
    {
        $data = $this->lifecycleData();
        $first = $this->worker('renew', $data, '@portal');
        $this->assertFetching($first);
        try {
            $this->assertSame('rate_limited', $this->finish($this->worker('renew', $data))['code']);
            Cache::lock('renewal:lock:enrollment:'.$this->binding->enrollment_id, 120)->forceRelease();
            $this->assertSame('rate_limited', $this->finish($this->worker('renew', $this->lifecycleData()))['code']);
            $this->assertSame('blacklist.checked', $this->finish($this->worker('query', $this->queryData()))['code']);
            $this->assertTrue($first->isRunning());
            $this->assertSame(1, $this->binding->fresh()->fetch_count);
            $this->assertSame('pending', DB::table('renewal_receipts')->where('enrollment_id', $this->binding->enrollment_id)->value('outcome'));
        } finally {
            $first->release();
        }
        $result = $this->finish($first, false);
        $this->assertSame('auth.renewed', $result['code']);
        $this->assertSame(1, $result['portal_calls']);
        Carbon::setTestNow(now()->addMinute());
        $replay = $this->finish($this->worker('renew', $data));
        $this->assertSame('auth.renewed', $replay['code']);
        $this->assertSame(0, $replay['portal_calls']);
        $this->assertSame(2, $this->binding->fresh()->lifecycle_version);
    }

    public function test_offboarding_commit_is_terminal_but_query_during_fetch_is_allowed(): void
    {
        $data = $this->lifecycleData();
        $first = $this->worker('renew', $data, '@portal', '<h2 class="alert-heading">Vypnuty zadavatelom</h2>');
        $this->assertFetching($first);
        try {
            $this->assertSame('blacklist.checked', $this->finish($this->worker('query', $this->queryData()))['code']);
        } finally {
            $first->release();
        }
        $this->assertSame('device_offboarded', $this->finish($first, false)['code']);
        $this->assertSame('device_offboarded', $this->finish($this->worker('query', $this->queryData()))['code']);
        $this->assertSame('device_offboarded', $this->finish($this->worker('renew', $data))['code']);
        $this->assertSame('offboarded', $this->binding->fresh()->lifecycle_state);
    }

    public function test_explicit_verification_during_fetch_fences_old_result(): void
    {
        $first = $this->worker('renew', $this->lifecycleData(), '@portal');
        $this->assertFetching($first);
        try {
            $verification = $this->verification($this->binding->phone_number);
            $this->assertSame('auth.verified', $this->finish($this->worker('verify', $verification))['code']);
            $this->assertNotSame($this->binding->enrollment_id, $this->binding->fresh()->enrollment_id);
        } finally {
            $first->release();
        }
        $this->assertSame('auth.enrollment_changed', $this->finish($first, false)['code']);
        $current = $this->binding->fresh();
        $this->assertNull($current->last_renewed_at);
        $this->assertSame('active', $current->lifecycle_state);
        $this->assertSame(0, $current->fetch_count);
        $this->assertSame(2, DB::table('device_enrollment_generations')->where('key_hash', hash('sha256', $this->publicKey))->count());
    }

    public function test_expired_pending_claim_cannot_overwrite_a_later_renewal(): void
    {
        $first = $this->worker('renew', $this->lifecycleData(), '@portal');
        $this->assertFetching($first);
        try {
            Carbon::setTestNow(now()->addSeconds(121));
            $retry = $this->lifecycleData();
            $this->assertSame('rate_limited', $this->finish($this->worker('renew', $retry))['code']);
            $this->assertSame(1, $this->binding->fresh()->renewal_failures);
            $this->assertSame(now()->addSeconds(900)->toDateTimeString(), $this->binding->fresh()->retry_at->toDateTimeString());
            Carbon::setTestNow(now()->addSeconds(900));
            $result = $this->finish($this->worker('renew', $retry));
            $this->assertSame('auth.renewed', $result['code']);
            $this->assertSame(1, $result['portal_calls']);
        } finally {
            $first->release();
        }
        $this->assertSame('auth.renewal_cycle_changed', $this->finish($first, false)['code']);
        $this->assertSame(2, $this->binding->fresh()->lifecycle_version);
        $this->assertSame(2, $this->binding->fresh()->fetch_count);
    }

    public function test_selection_serializes_short_authorization_transactions(): void
    {
        $first = $this->worker('status', $this->lifecycleData('status'), 'from `device_bindings`');
        $locked = $first->read();
        $this->assertSame('locked', $locked['event']);
        $this->assertSame(1, $locked['transaction_level']);
        $second = $this->worker('query', $this->queryData());
        $started = $second->read();
        $this->assertSame('started', $started['event']);
        try {
            $this->assertLockWait($started['connection_id'], $second, $locked['connection_id']);
        } finally {
            $first->release();
        }
        $this->assertSame('auth.status', $this->finish($first, false)['code']);
        $this->assertSame('blacklist.checked', $this->finish($second, false)['code']);
    }

    public function test_nullable_mariadb_upgrade_and_rollback_preserve_legacy_and_reports(): void
    {
        $client = Client::create(['client_hash' => hash('sha256', 'fixture-migration-target')]);
        Report::create(['client_id' => $client->id, 'reporter_hash' => hash('sha256', 'fixture-migration-reporter'), 'feature' => 'aggressive']);
        $migration = require database_path('migrations/2026_11_03_210000_add_binding_lifecycle.php');
        $verified = $this->binding->verified_at->toIso8601String();
        $migration->down();
        $this->assertSame($this->publicKey, DB::table('device_bindings')->where('id', $this->binding->id)->value('public_key'));
        $migration->up();
        $legacy = $this->binding->fresh();
        $this->assertNull($legacy->enrollment_id);
        $this->assertNull($legacy->ad_url);
        $this->assertNull($legacy->lifecycle_state);
        $this->assertSame($verified, $legacy->verified_at->toIso8601String());
        $this->assertSame(0, $legacy->fetch_count);
        $this->assertTrue(Report::where('client_id', $client->id)->exists());
        $this->assertTrue(Client::whereKey($client->id)->exists());
        app(BindingLifecycle::class)->withSelection($this->publicKey, function ($binding): void {
            app(BindingLifecycle::class)->initializeLegacy($binding);
            $this->assertSame('legacy', $binding->enrollment_kind);
            $this->assertNull($binding->next_renewal_at);
        });
    }
}
