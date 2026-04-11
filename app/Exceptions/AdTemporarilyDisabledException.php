<?php

namespace App\Exceptions;

class AdTemporarilyDisabledException extends ApiDomainException
{
    public static function create(): self
    {
        return new self(
            apiCode: 'ad_temporarily_disabled',
            message: 'The provided ad is temporarily disabled by its owner.',
            status: 400,
            errors: [
                'ad_url' => ['The provided ad is temporarily disabled by its owner.'],
            ],
            meta: [
                'retryable' => false,
                'ad_state' => 'temporarily_disabled',
            ],
        );
    }
}
