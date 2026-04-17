<?php

namespace App\Exceptions;

class SmsDispatchFailedException extends ApiDomainException
{
    public static function create(string $message = 'The verification SMS could not be sent.', array $context = []): self
    {
        return new self(
            apiCode: 'sms_dispatch_failed',
            message: $message,
            status: 503,
            meta: ['retryable' => true],
            logContext: $context,
        );
    }
}
