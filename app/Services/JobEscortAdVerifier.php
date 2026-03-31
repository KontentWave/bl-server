<?php

namespace App\Services;

use App\Contracts\EscortAdVerifier;
use App\Jobs\VerifyEscortAdJob;

class JobEscortAdVerifier implements EscortAdVerifier
{
    public function hasActiveAdForPhoneNumber(string $phoneNumber): bool
    {
        $job = new VerifyEscortAdJob($phoneNumber);

        return app()->call([$job, 'handle']);
    }
}
