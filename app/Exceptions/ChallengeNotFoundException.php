<?php

namespace App\Exceptions;

class ChallengeNotFoundException extends ApiDomainException
{
    public static function create(): self
    {
        return new self(
            apiCode: 'challenge_not_found',
            message: 'The verification challenge could not be found.',
            status: 422,
            errors: [
                'challenge_id' => ['The verification challenge could not be found.'],
            ],
            meta: ['retryable' => false],
        );
    }
}
