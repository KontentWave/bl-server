<?php

namespace App\Exceptions;

class ClientPhoneNumberInvalidException extends ApiDomainException
{
    public static function create(): self
    {
        return new self(
            apiCode: 'client_phone_number_invalid',
            message: 'The provided client phone number is invalid or unsupported.',
            status: 422,
            errors: [
                'client_phone_number' => ['The provided client phone number is invalid or unsupported.'],
            ],
            meta: ['retryable' => false],
        );
    }
}
