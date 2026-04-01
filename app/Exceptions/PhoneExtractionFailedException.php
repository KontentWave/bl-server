<?php

namespace App\Exceptions;

class PhoneExtractionFailedException extends ApiDomainException
{
    public static function create(): self
    {
        return new self(
            apiCode: 'phone_extraction_failed',
            message: 'The system could not extract a valid phone number from the supplied ad.',
            status: 400,
            errors: [
                'ad_url' => ['The system could not extract a valid phone number from the supplied ad.'],
            ],
            meta: ['retryable' => false],
        );
    }
}
