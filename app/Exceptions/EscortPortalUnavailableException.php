<?php

namespace App\Exceptions;

use App\Support\PhoneNumberRedactor;

class EscortPortalUnavailableException extends ApiDomainException
{
    public static function forPhoneNumber(string $phoneNumber, ?int $statusCode = null): self
    {
        return new self(
            apiCode: 'escort_portal_unavailable',
            message: 'Escort portal verification is currently unavailable.',
            status: 503,
            meta: array_filter([
                'retryable' => true,
                'upstream_status' => $statusCode,
            ], static fn (mixed $value): bool => $value !== null),
            logLevel: 'error',
            logContext: array_filter([
                'phone_number' => PhoneNumberRedactor::redact($phoneNumber),
                'upstream_status' => $statusCode,
            ], static fn (mixed $value): bool => $value !== null),
        );
    }
}
