<?php

namespace Tests\Database;

use App\Exceptions\ApiDomainException;
use App\Models\DeviceBinding;
use App\Models\OtpChallenge;
use App\Services\AuthVerificationService;
use App\Services\DeviceSignatureService;
use Illuminate\Cache\RateLimiter;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Database\QueryException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\Support\MariaDbWorker;
use Tests\TestCase;

class OtpConcurrencyTest extends TestCase
{
    private static int $clock = 0;

    private array $workers = [];

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::create(2026, 10, 3, 12, 0, 0, 'UTC')->addMinutes(2 * ++self::$clock));
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
        $engines = DB::select("SELECT ENGINE AS engine FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('otp_challenges', 'device_bindings', 'cache', 'cache_locks')");
        $this->assertCount(4, $engines);
        $this->assertSame(['InnoDB'], array_values(array_unique(array_column($engines, 'engine'))));
        config()->set('database.connections.lock_observer', array_replace(
            config('database.connections.mariadb'),
            ['username' => 'root'],
        ));
        config()->set('security.resend_cooldown_seconds', 1);
    }

    protected function tearDown(): void
    {
        foreach ($this->workers as $worker) {
            $worker->stop();
        }

        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_parallel_verifications_authorize_exactly_one_key(): void
    {
        [$challenge, $otp] = $this->challenge();
        $firstPayload = $this->payload($challenge, $otp);
        $secondPayload = $this->payload($challenge, $otp);

        $first = $this->startWorker('verify', $firstPayload, pauseAfterLock: true);
        $this->assertLocked($first);
        $second = $this->startWorker('verify', $secondPayload);
        $secondStarted = $this->readMessage($second);

        try {
            $this->assertLockWait($secondStarted['connection_id'], $second);
        } finally {
            $first->release();
        }

        $firstResult = $this->finishWorker($first);
        $secondResult = $this->finishWorker($second);

        $this->assertSame('auth.verified', $firstResult['code']);
        $this->assertSame('challenge_not_found', $secondResult['code']);
        $this->assertSame($firstPayload['publicKey'], DeviceBinding::where('phone_number', $challenge->phone_number)->sole()->public_key);
        $this->assertFalse(OtpChallenge::where('challenge_id', $challenge->challenge_id)->exists());
        $this->assertSame(2, app(RateLimiter::class)->attempts('otp:verify:'.hash('sha256', $challenge->challenge_id)));
    }

    public function test_resend_waits_for_verification_and_preserves_the_new_challenge(): void
    {
        [$challenge, $otp] = $this->challenge('+421900123456');
        $payload = $this->payload($challenge, $otp);
        $verifier = $this->startWorker('verify', $payload, pauseAfterLock: true);
        $this->assertLocked($verifier);
        $resender = $this->startWorker('resend');
        $resendStarted = $this->readMessage($resender);

        try {
            $this->assertLockWait($resendStarted['connection_id'], $resender);
        } finally {
            $verifier->release();
        }

        $verified = $this->finishWorker($verifier);
        $resent = $this->finishWorker($resender);

        $this->assertSame('auth.verified', $verified['code']);
        $this->assertSame('auth.sms_initiated', $resent['code']);
        $this->assertNotSame($challenge->challenge_id, $resent['challenge_id']);
        $this->assertSame($resent['challenge_id'], OtpChallenge::where('phone_number', $challenge->phone_number)->sole()->challenge_id);
        $this->assertSame($payload['publicKey'], DeviceBinding::where('phone_number', $challenge->phone_number)->sole()->public_key);
    }

    public function test_verification_waits_for_resend_and_cannot_consume_its_replacement(): void
    {
        [$challenge, $otp] = $this->challenge('+421900123456');
        $payload = $this->payload($challenge, $otp);
        $bindingBefore = DeviceBinding::where('phone_number', $challenge->phone_number)->first()?->public_key;
        $resender = $this->startWorker('resend', pauseAfterLock: true);
        $this->assertLocked($resender);
        $verifier = $this->startWorker('verify', $payload);
        $verificationStarted = $this->readMessage($verifier);

        try {
            $this->assertLockWait($verificationStarted['connection_id'], $verifier);
        } finally {
            $resender->release();
        }

        $resent = $this->finishWorker($resender);
        $verified = $this->finishWorker($verifier);

        $this->assertSame('auth.sms_initiated', $resent['code']);
        $this->assertSame('challenge_not_found', $verified['code']);
        $this->assertSame($resent['challenge_id'], OtpChallenge::where('phone_number', $challenge->phone_number)->sole()->challenge_id);
        $this->assertSame($bindingBefore, DeviceBinding::where('phone_number', $challenge->phone_number)->first()?->public_key);
    }

    public function test_consumption_failure_rolls_back_an_existing_binding_and_keeps_the_challenge(): void
    {
        [$challenge, $otp] = $this->challenge();
        $payload = $this->payload($challenge, $otp);
        $binding = DeviceBinding::create([
            'phone_number' => $challenge->phone_number,
            'public_key' => 'existing-synthetic-key',
            'verified_at' => now()->subDay(),
        ]);
        $originalTime = $binding->verified_at;
        DB::statement("CREATE TRIGGER fail_otp_consumption BEFORE DELETE ON otp_challenges FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'synthetic consumption failure'");

        try {
            try {
                app(AuthVerificationService::class)->verify(...$payload);
                $this->fail('Expected challenge consumption to fail.');
            } catch (QueryException $exception) {
                $this->assertSame('45000', $exception->errorInfo[0]);
            }
        } finally {
            DB::statement('DROP TRIGGER fail_otp_consumption');
        }

        $this->assertSame('existing-synthetic-key', $binding->fresh()->public_key);
        $this->assertTrue($originalTime->equalTo($binding->fresh()->verified_at));
        $this->assertTrue(OtpChallenge::where('challenge_id', $challenge->challenge_id)->exists());
        $this->assertSame(1, app(RateLimiter::class)->attempts('otp:verify:'.hash('sha256', $challenge->challenge_id)));
        $this->assertSame('auth.verified', $this->verify($payload)['code']);
    }

    public function test_invalid_otp_attempts_remain_counted_in_the_database_cache(): void
    {
        [$challenge, $otp] = $this->challenge();
        $payload = $this->payload($challenge, $otp);
        $payload['otp'] = '000000';
        config()->set('security.otp_verification_attempts', 2);

        $this->assertSame('otp_invalid_or_expired', $this->verify($payload)['code']);
        $this->assertSame('otp_invalid_or_expired', $this->verify($payload)['code']);
        $payload['otp'] = $otp;
        $this->assertSame('rate_limited', $this->verify($payload)['code']);
        $this->assertTrue(OtpChallenge::where('challenge_id', $challenge->challenge_id)->exists());
        $this->assertFalse(DeviceBinding::where('phone_number', $challenge->phone_number)->exists());
    }

    private function challenge(?string $phoneNumber = null): array
    {
        $otp = '123456';
        $challenge = OtpChallenge::updateOrCreate(
            ['phone_number' => $phoneNumber ?? '+421900000'.str_pad((string) self::$clock, 3, '0', STR_PAD_LEFT)],
            [
                'challenge_id' => (string) Str::uuid(),
                'ad_url' => 'https://portal.example.test/escort/miriam',
                'otp_hash' => Hash::make($otp),
                'expires_at' => now()->addMinutes(15),
            ],
        );

        return [$challenge, $otp];
    }

    private function payload(OtpChallenge $challenge, string $otp): array
    {
        $privateKey = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
        $publicKey = app(DeviceSignatureService::class)->normalizePublicKey(openssl_pkey_get_details($privateKey)['key']);
        openssl_sign(app(DeviceSignatureService::class)->payload($challenge->challenge_id, $publicKey), $signature, $privateKey, OPENSSL_ALGO_SHA256);

        return [
            'challengeId' => $challenge->challenge_id,
            'otp' => $otp,
            'publicKey' => $publicKey,
            'signature' => base64_encode($signature),
        ];
    }

    private function verify(array $payload): array
    {
        try {
            app(AuthVerificationService::class)->verify(...$payload);

            return ['code' => 'auth.verified'];
        } catch (ApiDomainException $exception) {
            return ['code' => $exception->apiCode()];
        } catch (ThrottleRequestsException) {
            return ['code' => 'rate_limited'];
        }
    }

    private function startWorker(string $operation, array $payload = [], bool $pauseAfterLock = false): MariaDbWorker
    {
        $worker = new MariaDbWorker($operation, $payload, $pauseAfterLock, now()->toIso8601String());
        $this->workers[] = $worker;

        return $worker;
    }

    private function readMessage(MariaDbWorker $channel): array
    {
        $message = $channel->read();
        $this->assertNotSame('failed', $message['event'], 'Worker failure: '.json_encode($message));

        return $message;
    }

    private function assertLocked(MariaDbWorker $worker): void
    {
        $message = $this->readMessage($worker);
        $this->assertSame('locked', $message['event']);
        $this->assertSame(1, $message['transaction_level']);
    }

    private function finishWorker(MariaDbWorker $worker): array
    {
        $result = $this->readMessage($worker);
        $this->assertSame(0, $worker->wait(), 'Worker failure: '.json_encode($result));
        $this->assertSame('finished', $result['event']);

        return $result;
    }

    private function assertLockWait(int $connectionId, MariaDbWorker $worker): void
    {
        $deadline = microtime(true) + 10;

        do {
            if (! $worker->isRunning()) {
                $this->fail('Worker completed before entering a lock wait: '.json_encode($worker->read()));
            }

            $waiting = DB::connection('lock_observer')->selectOne(
                'SELECT COUNT(*) AS waiting FROM information_schema.INNODB_LOCK_WAITS w JOIN information_schema.INNODB_TRX t ON t.trx_id = w.requesting_trx_id WHERE t.trx_mysql_thread_id = ?',
                [$connectionId],
            )->waiting;

            if ((int) $waiting > 0) {
                $this->addToAssertionCount(1);

                return;
            }

            // Allow InnoDB's information-schema snapshot to refresh between observations.
            usleep(250000);
        } while (microtime(true) < $deadline);

        $transactions = DB::connection('lock_observer')->select('SELECT trx_mysql_thread_id, trx_state FROM information_schema.INNODB_TRX');
        $connections = DB::connection('lock_observer')->select("SELECT ID, COMMAND, STATE, REGEXP_SUBSTR(INFO, 'from `?[a-z_]+`?') AS query_table FROM information_schema.PROCESSLIST WHERE USER = 'beta_otp_test'");
        $rowWaits = DB::connection('lock_observer')->select("SHOW GLOBAL STATUS LIKE 'Innodb_row_lock_current_waits'");
        $this->fail('Expected a genuine overlapping InnoDB row-lock wait for connection '.$connectionId.'. Observed transactions: '.json_encode($transactions).'. Connection states: '.json_encode($connections).'. Row waits: '.json_encode($rowWaits));
    }
}
