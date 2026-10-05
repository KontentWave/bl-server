<?php

namespace Tests\Database;

use App\Models\Client;
use App\Models\ClientFeatureLevel;
use App\Models\DeviceBinding;
use App\Models\Report;
use App\Services\DeviceSignatureService;
use App\Services\ReportSubmissionService;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Support\AssertsMariaDbLockWait;
use Tests\Support\MariaDbWorker;
use Tests\TestCase;

class ReportConcurrencyTest extends TestCase
{
    use AssertsMariaDbLockWait {
        assertLockWait as private assertObservedLockWait;
    }

    private array $workers = [];

    private array $queries = [];

    private static int $identity = 0;

    private int $blockingConnectionId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->assertSame('mariadb', DB::getDefaultConnection());
        $this->assertSame('beta_otp_test', DB::connection()->getDatabaseName());
        $this->assertMatchesRegularExpression('/^11\.4\./', DB::selectOne('SELECT VERSION() AS version')->version);
        $this->assertSame(getenv('BETA_TEST_ISOLATION'), DB::selectOne('SELECT @@tx_isolation AS isolation_level')->isolation_level);
        $this->assertSame('log', config('services.sms.driver'));
        $this->assertFalse(config('services.sms.log_otp_in_non_production'));
        Http::preventStrayRequests();
        config()->set('security.api_per_minute', 10000);

        $migrator = app(Migrator::class);

        if (! $migrator->repositoryExists()) {
            $this->assertSame([], DB::select('SHOW TABLES'));
            $migrator->getRepository()->createRepository();
        }

        $migrator->run([database_path('migrations')]);
        $engines = DB::select("SELECT ENGINE AS engine FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('clients', 'reports', 'client_feature_levels')");
        $this->assertCount(3, $engines);
        $this->assertSame(['InnoDB'], array_values(array_unique(array_column($engines, 'engine'))));
        config()->set('database.connections.lock_observer', array_replace(
            config('database.connections.mariadb'),
            ['username' => 'root'],
        ));
    }

    protected function tearDown(): void
    {
        foreach ($this->workers as $worker) {
            $worker->stop();
        }

        parent::tearDown();
    }

    public function test_parallel_second_and_third_reports_promote_with_an_exact_count(): void
    {
        $phone = $this->clientPhone();
        $this->submit($this->payload($phone));
        $first = $this->startWorker($this->payload($phone), 'select count(*)');
        $this->assertPaused($first);
        $second = $this->startWorker($this->payload($phone));
        $started = $this->message($second);
        $this->assertSame('started', $started['event']);

        try {
            $this->assertLockWait($started['connection_id'], $second);
            $this->assertState($phone, 'no_show', 1);
            $this->assertQuery($phone, []);
        } finally {
            $first->release();
        }

        $firstResult = $this->finish($first);
        $secondResult = $this->finish($second);
        $this->assertCreated($firstResult, 2);
        $this->assertCreated($secondResult, 3);
        $this->assertState($phone, 'no_show', 3);
        $this->assertQuery($phone, ['No-Show']);
    }

    public function test_racing_identical_reports_keep_the_duplicate_http_envelope(): void
    {
        $phone = $this->clientPhone();
        Client::create(['client_hash' => hash('sha256', $phone)]);
        $this->raceIdentical($phone, 'select count(*)');
    }

    public function test_concurrent_first_client_creation_with_distinct_reporters(): void
    {
        $phone = $this->clientPhone();
        $first = $this->startWorker($this->payload($phone), 'insert into `clients`');
        $this->assertPaused($first);
        $second = $this->startWorker($this->payload($phone));
        $started = $this->message($second);

        try {
            $this->assertLockWait($started['connection_id'], $second);
            $this->assertFalse(Client::where('client_hash', hash('sha256', $phone))->exists());
        } finally {
            $first->release();
        }

        $this->assertCreated($this->finish($first), 1);
        $this->assertCreated($this->finish($second), 2);
        $this->assertState($phone, 'no_show', 2);
    }

    public function test_concurrent_first_client_creation_with_identical_reports(): void
    {
        $this->raceIdentical($this->clientPhone(), 'insert into `clients`');
    }

    public function test_concurrent_first_feature_creation_does_not_change_another_feature(): void
    {
        $phone = $this->clientPhone();
        $this->submit($this->payload($phone));
        $first = $this->startWorker($this->payload($phone, 'aggressive'), 'select count(*)');
        $this->assertPaused($first);
        $second = $this->startWorker($this->payload($phone, 'aggressive'));
        $started = $this->message($second);

        try {
            $this->assertLockWait($started['connection_id'], $second);
        } finally {
            $first->release();
        }

        $this->assertCreated($this->finish($first), 1);
        $this->assertCreated($this->finish($second), 2);
        $this->assertState($phone, 'no_show', 1);
        $this->assertState($phone, 'aggressive', 2);
        $this->assertQuery($phone, []);
    }

    public function test_parallel_independent_features_promote_only_their_own_counts(): void
    {
        $phone = $this->clientPhone();
        foreach (['no_show', 'aggressive'] as $feature) {
            $this->submit($this->payload($phone, $feature));
            $this->submit($this->payload($phone, $feature));
        }

        $first = $this->startWorker($this->payload($phone), 'select count(*)');
        $this->assertPaused($first);
        $second = $this->startWorker($this->payload($phone, 'aggressive'));
        $started = $this->message($second);

        try {
            $this->assertLockWait($started['connection_id'], $second);
            $this->assertQuery($phone, []);
        } finally {
            $first->release();
        }

        $this->assertCreated($this->finish($first), 3);
        $this->assertCreated($this->finish($second), 3);
        $this->assertState($phone, 'no_show', 3);
        $this->assertState($phone, 'aggressive', 3);
        $this->assertQuery($phone, ['Aggressive', 'No-Show']);
    }

    public function test_an_independent_client_can_commit_while_another_client_is_locked(): void
    {
        $firstPhone = $this->clientPhone();
        $secondPhone = $this->clientPhone();
        foreach ([$firstPhone, $secondPhone] as $phone) {
            $this->submit($this->payload($phone));
            $this->submit($this->payload($phone));
        }

        $first = $this->startWorker($this->payload($firstPhone), 'select count(*)');
        $this->assertPaused($first);
        $second = $this->startWorker($this->payload($secondPhone));
        $this->assertSame('started', $this->message($second)['event']);

        try {
            $this->assertCreated($this->finish($second), 3);
            $this->assertTrue($first->isRunning());
            $this->assertState($firstPhone, 'no_show', 2);
            $this->assertState($secondPhone, 'no_show', 3);
            $this->assertQuery($firstPhone, []);
            $this->assertQuery($secondPhone, ['No-Show']);
        } finally {
            $first->release();
        }

        $this->assertCreated($this->finish($first), 3);
        $this->assertState($firstPhone, 'no_show', 3);
        $this->assertState($secondPhone, 'no_show', 3);
    }

    public function test_uncommitted_promotion_is_invisible_until_the_report_transaction_commits(): void
    {
        $phone = $this->clientPhone();
        $this->submit($this->payload($phone));
        $this->submit($this->payload($phone));
        $worker = $this->startWorker($this->payload($phone), 'update `client_feature_levels`');
        $this->assertPaused($worker);

        try {
            $this->assertState($phone, 'no_show', 2);
            $this->assertQuery($phone, []);
        } finally {
            $worker->release();
        }

        $this->assertCreated($this->finish($worker), 3);
        $this->assertState($phone, 'no_show', 3);
        $this->assertQuery($phone, ['No-Show']);
    }

    public function test_promotion_persistence_failure_rolls_back_the_report_and_count(): void
    {
        $phone = $this->clientPhone();
        $this->submit($this->payload($phone));
        $this->submit($this->payload($phone));
        $payload = $this->payload($phone);
        DB::statement("CREATE TRIGGER fail_report_promotion BEFORE UPDATE ON client_feature_levels FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'synthetic promotion failure'");

        try {
            $this->assertPersistenceFailure($payload);
        } finally {
            DB::statement('DROP TRIGGER fail_report_promotion');
        }

        $this->assertState($phone, 'no_show', 2);
        $this->assertQuery($phone, []);
        $this->submit($payload);
        $this->assertState($phone, 'no_show', 3);
        $this->assertQuery($phone, ['No-Show']);
    }

    public function test_first_feature_persistence_failure_rolls_back_the_new_client_and_report(): void
    {
        $phone = $this->clientPhone();
        $payload = $this->payload($phone);
        $reportsBefore = Report::count();
        DB::statement("CREATE TRIGGER fail_first_report_feature BEFORE INSERT ON client_feature_levels FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'synthetic feature failure'");

        try {
            $this->assertPersistenceFailure($payload);
        } finally {
            DB::statement('DROP TRIGGER fail_first_report_feature');
        }

        $this->assertFalse(Client::where('client_hash', hash('sha256', $phone))->exists());
        $this->assertSame($reportsBefore, Report::count());
        $this->assertQuery($phone, []);
        $this->submit($payload);
        $this->assertState($phone, 'no_show', 1);
    }

    public function test_transient_concurrency_errors_retry_database_work_at_most_three_times(): void
    {
        $phone = $this->clientPhone();
        $payload = $this->payload($phone);
        DB::statement('SET @report_attempts = 0');
        DB::unprepared("CREATE TRIGGER retry_report_insert BEFORE INSERT ON reports FOR EACH ROW BEGIN SET @report_attempts = @report_attempts + 1; IF @report_attempts <= 2 THEN SIGNAL SQLSTATE '40001' SET MYSQL_ERRNO = 1213, MESSAGE_TEXT = 'synthetic serialization failure'; END IF; END");

        try {
            $this->submit($payload);
            $this->assertSame(3, (int) DB::selectOne('SELECT @report_attempts AS attempts')->attempts);
        } finally {
            DB::statement('DROP TRIGGER retry_report_insert');
        }

        $this->assertState($phone, 'no_show', 1);
    }

    public function test_exhausted_concurrency_retries_surface_the_failure_and_leave_no_partial_state(): void
    {
        $phone = $this->clientPhone();
        $payload = $this->payload($phone);
        $reportsBefore = Report::count();
        DB::statement('SET @report_attempts = 0');
        DB::unprepared("CREATE TRIGGER exhaust_report_insert BEFORE INSERT ON reports FOR EACH ROW BEGIN SET @report_attempts = @report_attempts + 1; SIGNAL SQLSTATE '40001' SET MYSQL_ERRNO = 1213, MESSAGE_TEXT = 'synthetic serialization failure'; END");

        try {
            try {
                $this->submitService($payload);
                $this->fail('Expected exhausted retries to surface the database failure.');
            } catch (QueryException $exception) {
                $this->assertSame('40001', $exception->errorInfo[0]);
                $this->assertSame(1213, $exception->errorInfo[1]);
            }
            $this->assertSame(3, (int) DB::selectOne('SELECT @report_attempts AS attempts')->attempts);
        } finally {
            DB::statement('DROP TRIGGER exhaust_report_insert');
        }

        $this->assertFalse(Client::where('client_hash', hash('sha256', $phone))->exists());
        $this->assertSame($reportsBefore, Report::count());
        $this->assertQuery($phone, []);
    }

    public function test_current_report_reads_ignore_an_older_repeatable_read_snapshot(): void
    {
        $phone = $this->clientPhone();
        $this->submit($this->payload($phone));
        $secondPayload = $this->payload($phone);
        $thirdPayload = $this->payload($phone);
        DB::beginTransaction();

        try {
            $this->assertState($phone, 'no_show', 1);
            $worker = $this->startWorker($secondPayload);
            $this->assertSame('started', $this->message($worker)['event']);
            $this->assertCreated($this->finish($worker), 2);
            $result = $this->submitService($thirdPayload);
            $this->assertSame(3, $result['unique_reporter_count']);
            $this->assertSame('level_2', $result['level']);
            DB::commit();
        } finally {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
        }

        $this->assertState($phone, 'no_show', 3);
        $this->assertQuery($phone, ['No-Show']);
    }

    private function raceIdentical(string $phone, string $pauseQuery): void
    {
        $payload = $this->payload($phone);
        $first = $this->startWorker($payload, $pauseQuery);
        $this->assertPaused($first);
        $second = $this->startWorker($payload);
        $started = $this->message($second);

        try {
            $this->assertLockWait($started['connection_id'], $second);
        } finally {
            $first->release();
        }

        $this->assertCreated($this->finish($first), 1);
        $duplicate = $this->finish($second);
        $this->assertSame(422, $duplicate['status']);
        $this->assertSame([
            'success' => false,
            'code' => 'duplicate_report',
            'message' => 'You have already reported this client for the selected feature.',
            'errors' => ['feature' => ['You have already reported this client for the selected feature.']],
            'meta' => ['retryable' => false, 'feature' => 'no_show'],
        ], $duplicate['body']);
        $this->assertState($phone, 'no_show', 1);
    }

    private function clientPhone(): string
    {
        return '+421901'.str_pad((string) ++self::$identity, 6, '0', STR_PAD_LEFT);
    }

    private function payload(string $phone, string $feature = 'no_show'): array
    {
        $key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
        $service = app(DeviceSignatureService::class);
        $publicKey = $service->normalizePublicKey(openssl_pkey_get_details($key)['key']);
        DeviceBinding::create([
            'phone_number' => $this->clientPhone(),
            'public_key' => $publicKey,
            'verified_at' => now(),
        ]);
        openssl_sign($service->reportPayload($phone, $feature, $publicKey), $signature, $key, OPENSSL_ALGO_SHA256);
        $targetHash = hash('sha256', $phone);
        openssl_sign($service->blacklistCheckPayload($targetHash, $publicKey), $querySignature, $key, OPENSSL_ALGO_SHA256);
        $this->queries[$phone] = [
            'target_hash' => $targetHash,
            'public_key' => $publicKey,
            'signature' => base64_encode($querySignature),
        ];

        return [
            'client_phone_number' => $phone,
            'feature' => $feature,
            'public_key' => $publicKey,
            'signature' => base64_encode($signature),
        ];
    }

    private function submit(array $payload): void
    {
        $this->postJson('/api/reports', $payload)->assertCreated();
    }

    private function startWorker(array $payload, ?string $pauseQuery = null): MariaDbWorker
    {
        $worker = new MariaDbWorker('report', $payload, false, now()->toIso8601String(), $pauseQuery);
        $this->workers[] = $worker;

        return $worker;
    }

    private function message(MariaDbWorker $worker): array
    {
        $message = $worker->read();
        $this->assertNotSame('failed', $message['event'], 'Worker failure: '.json_encode($message));

        return $message;
    }

    private function assertPaused(MariaDbWorker $worker): void
    {
        $message = $this->message($worker);
        $this->assertSame('locked', $message['event']);
        $this->assertSame(1, $message['transaction_level']);
        $this->blockingConnectionId = $message['connection_id'];
    }

    private function finish(MariaDbWorker $worker): array
    {
        $result = $this->message($worker);
        $this->assertSame(0, $worker->wait());
        $this->assertSame('finished', $result['event']);

        return $result;
    }

    private function assertLockWait(int $connectionId, MariaDbWorker $worker): void
    {
        $this->assertObservedLockWait($connectionId, $worker, $this->blockingConnectionId);
    }

    private function assertState(string $phone, string $feature, int $count): void
    {
        $client = Client::where('client_hash', hash('sha256', $phone))->sole();
        $this->assertSame($count, Report::where('client_id', $client->id)->where('feature', $feature)->count());
        $level = ClientFeatureLevel::where('client_id', $client->id)->where('feature', $feature)->sole();
        $this->assertSame($count, $level->unique_reporter_count);
        $this->assertSame($count >= 3, $level->is_level_two);
        $this->assertSame($count >= 3, $level->promoted_at !== null);
    }

    private function assertCreated(array $result, int $count): void
    {
        $this->assertSame(201, $result['status']);
        $this->assertSame(true, $result['body']['success']);
        $this->assertSame('report.created', $result['body']['code']);
        $this->assertSame($count, $result['body']['data']['unique_reporter_count']);
        $this->assertSame($count >= 3 ? 'level_2' : 'level_1', $result['body']['data']['level']);
        $this->assertSame($count >= 3, $result['body']['data']['ready_for_sync']);
        $this->assertSame(['client_hash', 'reporter_hash', 'feature', 'feature_label', 'unique_reporter_count', 'level', 'ready_for_sync'], array_keys($result['body']['data']));
    }

    private function assertQuery(string $phone, array $features): void
    {
        $this->postJson('/api/blacklist/check', $this->queries[$phone])
            ->assertOk()
            ->assertJsonPath('code', 'blacklist.checked')
            ->assertJsonPath('data.features', $features);
    }

    private function assertPersistenceFailure(array $payload): void
    {
        $insertions = 0;
        DB::listen(function ($query) use (&$insertions): void {
            if (str_starts_with($query->sql, 'insert into `reports`')) {
                $insertions++;
            }
        });

        try {
            $this->submitService($payload);
            $this->fail('Expected feature persistence to fail.');
        } catch (QueryException $exception) {
            $this->assertSame('45000', $exception->errorInfo[0]);
        }

        $this->assertSame(1, $insertions, 'Non-concurrency persistence failures must not be retried.');
    }

    private function submitService(array $payload): array
    {
        return app(ReportSubmissionService::class)->submit(
            $payload['client_phone_number'],
            $payload['feature'],
            $payload['public_key'],
            $payload['signature'],
        );
    }
}
