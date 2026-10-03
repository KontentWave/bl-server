<?php

use App\Exceptions\ApiDomainException;
use App\Services\AuthVerificationService;
use App\Services\OtpChallengeService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\QueryException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

require dirname(__DIR__).'/bootstrap-mariadb.php';

$application = require dirname(__DIR__, 2).'/bootstrap/app.php';
$application->make(Kernel::class)->bootstrap();
Http::preventStrayRequests();
stream_set_timeout(STDIN, 20);

try {
    $input = fgets(STDIN);

    if ($input === false) {
        throw new RuntimeException('Missing worker operation.');
    }

    $request = json_decode($input, true, flags: JSON_THROW_ON_ERROR);
    Carbon::setTestNow(Carbon::parse($request['now']));
    config()->set('security.resend_cooldown_seconds', 1);
    $connectionId = (int) DB::connection()->getPdo()->query('SELECT CONNECTION_ID()')->fetchColumn();

    if ($request['pause_after_lock']) {
        $paused = false;
        DB::listen(function ($query) use (&$paused): void {
            if (! $paused && str_contains($query->sql, 'otp_challenges') && str_contains($query->sql, 'for update')) {
                $paused = true;
                echo json_encode([
                    'event' => 'locked',
                    'transaction_level' => $query->connection->transactionLevel(),
                    'connection_id' => (int) $query->connection->getPdo()->query('SELECT CONNECTION_ID()')->fetchColumn(),
                ], JSON_THROW_ON_ERROR)."\n";
                flush();

                if (fgets(STDIN) !== "release\n") {
                    throw new RuntimeException('Timed out waiting for transaction release.');
                }
            }
        });
    } else {
        echo json_encode(['event' => 'started', 'connection_id' => $connectionId], JSON_THROW_ON_ERROR)."\n";
        flush();
    }

    try {
        $result = match ($request['operation']) {
            'verify' => (function () use ($request): array {
                app(AuthVerificationService::class)->verify(...$request['payload']);

                return ['code' => 'auth.verified'];
            })(),
            'resend' => (function (): array {
                [$challenge] = app(OtpChallengeService::class)->issue('https://portal.example.test/escort/miriam');

                return ['code' => 'auth.sms_initiated', 'challenge_id' => $challenge->challenge_id];
            })(),
            default => throw new RuntimeException('Unknown worker operation.'),
        };
    } catch (ApiDomainException $exception) {
        $result = ['code' => $exception->apiCode()];
    } catch (ThrottleRequestsException) {
        $result = ['code' => 'rate_limited'];
    }

    echo json_encode(['event' => 'finished'] + $result, JSON_THROW_ON_ERROR)."\n";
    exit(0);
} catch (Throwable $exception) {
    $failure = ['event' => 'failed', 'exception' => $exception::class];

    if ($exception instanceof QueryException) {
        $failure['sqlstate'] = $exception->errorInfo[0];
        $failure['driver_code'] = $exception->errorInfo[1];
    }

    echo json_encode($failure, JSON_THROW_ON_ERROR)."\n";
    exit(1);
}
