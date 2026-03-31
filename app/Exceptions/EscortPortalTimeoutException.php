<?php

namespace App\Exceptions;

use App\Support\PhoneNumberRedactor;

class EscortPortalTimeoutException extends ApiDomainException
{
    public static function forPhoneNumber(string $phoneNumber): self
    {
        return new self(
            apiCode: 'escort_portal_timeout',
            message: 'Escort portal verification timed out.',
            status: 503,
            meta: ['retryable' => true],
            logLevel: 'warning',
            logContext: ['phone_number' => PhoneNumberRedactor::redact($phoneNumber)],
        );
    }
}
