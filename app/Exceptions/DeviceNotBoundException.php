<?php

namespace App\Exceptions;

class DeviceNotBoundException extends ApiDomainException
{
    public static function create(): self
    {
        return new self(
            apiCode: 'device_not_bound',
            message: 'The provided device key is not bound to a verified worker.',
            status: 403,
            errors: [
                'public_key' => ['The provided device key is not bound to a verified worker.'],
            ],
            meta: ['retryable' => false],
        );
    }
}
