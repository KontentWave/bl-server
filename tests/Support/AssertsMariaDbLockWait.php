<?php

namespace Tests\Support;

use Illuminate\Support\Facades\DB;

trait AssertsMariaDbLockWait
{
    private function assertLockWait(int $connectionId, MariaDbWorker $worker, ?int $blockingConnectionId = null): void
    {
        if ($blockingConnectionId !== null) {
            $this->assertNotSame($blockingConnectionId, $connectionId);
        }

        $deadline = microtime(true) + 10;

        do {
            if (! $worker->isRunning()) {
                $this->fail('Worker completed before entering a lock wait: '.json_encode($worker->read()));
            }

            $waits = DB::connection('lock_observer')->select(
                'SELECT b.trx_mysql_thread_id AS blocking_connection FROM information_schema.INNODB_LOCK_WAITS w JOIN information_schema.INNODB_TRX t ON t.trx_id = w.requesting_trx_id JOIN information_schema.INNODB_TRX b ON b.trx_id = w.blocking_trx_id WHERE t.trx_mysql_thread_id = ?',
                [$connectionId],
            );

            foreach ($waits as $wait) {
                if ($blockingConnectionId === null || (int) $wait->blocking_connection === $blockingConnectionId) {
                    $this->addToAssertionCount(1);

                    return;
                }
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
