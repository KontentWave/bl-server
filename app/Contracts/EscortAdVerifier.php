<?php

namespace App\Contracts;

interface EscortAdVerifier
{
    public function hasActiveAdForPhoneNumber(string $phoneNumber): bool;
}
