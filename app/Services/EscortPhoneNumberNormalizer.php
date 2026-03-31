<?php

namespace App\Services;

class EscortPhoneNumberNormalizer
{
    public function normalize(string $phoneNumber): string
    {
        return preg_replace('/\D+/', '', $phoneNumber) ?? '';
    }
}
