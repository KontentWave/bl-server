<?php

namespace Tests\Support;

use RuntimeException;
use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process;

class MariaDbWorker
{
    private Process $process;

    private InputStream $input;

    private string $buffer = '';

    public function __construct(string $operation, array $payload, bool $pauseAfterLock, string $now)
    {
        $this->input = new InputStream;
        $this->input->write(json_encode([
            'operation' => $operation,
            'payload' => $payload,
            'pause_after_lock' => $pauseAfterLock,
            'now' => $now,
        ], JSON_THROW_ON_ERROR)."\n");
        $this->process = new Process([PHP_BINARY, base_path('tests/Support/mariadb-worker.php')], base_path());
        $this->process->setInput($this->input);
        $this->process->setTimeout(30);
        $this->process->start();
    }

    public function read(): array
    {
        $deadline = microtime(true) + 20;

        do {
            $this->process->checkTimeout();
            $this->buffer .= $this->process->getIncrementalOutput();
            $newline = strpos($this->buffer, "\n");

            if ($newline !== false) {
                $message = substr($this->buffer, 0, $newline);
                $this->buffer = substr($this->buffer, $newline + 1);

                return json_decode($message, true, flags: JSON_THROW_ON_ERROR);
            }

            if (! $this->process->isRunning()) {
                throw new RuntimeException('Database worker exited without a control message: '.$this->process->getErrorOutput());
            }

            usleep(10000);
        } while (microtime(true) < $deadline);

        throw new RuntimeException('Database worker timed out waiting for a control message.');
    }

    public function release(): void
    {
        $this->input->write("release\n");
    }

    public function isRunning(): bool
    {
        return $this->process->isRunning();
    }

    public function wait(): int
    {
        $this->input->close();

        return $this->process->wait();
    }

    public function stop(): void
    {
        $this->input->close();

        if ($this->process->isRunning()) {
            $this->process->stop(1);
        }
    }
}
