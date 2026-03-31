<?php

namespace App\Services;

use App\Contracts\EscortAdVerifier;

class NullEscortAdVerifier implements EscortAdVerifier
{
    public function hasActiveAdForPhoneNumber(string $phoneNumber): bool
    {
        return false;
    }
}
