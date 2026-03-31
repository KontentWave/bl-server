<?php

namespace App\Exceptions;

use RuntimeException;

class ApiDomainException extends RuntimeException
{
    public function __construct(
        private readonly string $apiCode,
        string $message,
        private readonly int $status,
        private readonly array $errors = [],
        private readonly array $meta = [],
        private readonly string $logLevel = 'warning',
        private readonly array $logContext = [],
    ) {
        parent::__construct($message);
    }

    public function apiCode(): string
    {
        return $this->apiCode;
    }

    public function status(): int
    {
        return $this->status;
    }

    public function errors(): array
    {
        return $this->errors;
    }

    public function meta(): array
    {
        return $this->meta;
    }

    public function logLevel(): string
    {
        return $this->logLevel;
    }

    public function logContext(): array
    {
        return $this->logContext;
    }
}
