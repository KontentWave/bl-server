<?php

namespace App\Exceptions;

class SignatureInvalidException extends ApiDomainException
{
    public static function create(): self
    {
        return new self(
            apiCode: 'signature_invalid',
            message: 'The provided device signature is invalid.',
            status: 422,
            errors: [
                'signature' => ['The provided device signature is invalid.'],
            ],
            meta: ['retryable' => false],
        );
    }
}
