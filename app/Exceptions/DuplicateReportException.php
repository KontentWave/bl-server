<?php

namespace App\Exceptions;

class DuplicateReportException extends ApiDomainException
{
    public static function create(string $feature): self
    {
        return new self(
            apiCode: 'duplicate_report',
            message: 'You have already reported this client for the selected feature.',
            status: 422,
            errors: [
                'feature' => ['You have already reported this client for the selected feature.'],
            ],
            meta: [
                'retryable' => false,
                'feature' => $feature,
            ],
        );
    }
}
