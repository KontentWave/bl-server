<?php

namespace App\Exceptions;

class InvalidAdUrlException extends ApiDomainException
{
    public static function create(): self
    {
        return new self(
            apiCode: 'invalid_ad_url',
            message: 'The provided ad URL is invalid or unsupported.',
            status: 400,
            errors: [
                'ad_url' => ['The provided ad URL is invalid or unsupported.'],
            ],
            meta: ['retryable' => false],
        );
    }
}
