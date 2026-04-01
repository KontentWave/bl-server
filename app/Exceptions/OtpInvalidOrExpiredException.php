<?php

namespace App\Exceptions;

class OtpInvalidOrExpiredException extends ApiDomainException
{
    public static function create(): self
    {
        return new self(
            apiCode: 'otp_invalid_or_expired',
            message: 'The provided OTP is invalid or expired.',
            status: 422,
            errors: [
                'otp' => ['The provided OTP is invalid or expired.'],
            ],
            meta: ['retryable' => false],
        );
    }
}
