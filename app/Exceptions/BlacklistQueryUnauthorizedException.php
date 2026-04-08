<?php

namespace App\Exceptions;

class BlacklistQueryUnauthorizedException extends ApiDomainException
{
    public static function create(): self
    {
        return new self(
            apiCode: 'blacklist_query_unauthorized',
            message: 'The blacklist query could not be authorized.',
            status: 401,
            errors: [
                'authorization' => ['The blacklist query could not be authorized.'],
            ],
            meta: ['retryable' => false],
        );
    }
}
