<?php

namespace App\Exceptions;

use App\Support\PhoneNumberRedactor;

class EscortAdNotVerifiedException extends ApiDomainException
{
    public static function forPhoneNumber(string $phoneNumber): self
    {
        return new self(
            apiCode: 'escort_ad_not_verified',
            message: 'No active escort advertisement could be verified for this phone number.',
            status: 422,
            errors: [
                'phone_number' => ['No active escort advertisement could be verified for this phone number.'],
            ],
            meta: ['retryable' => false],
            logLevel: 'warning',
            logContext: ['phone_number' => PhoneNumberRedactor::redact($phoneNumber)],
        );
    }
}
