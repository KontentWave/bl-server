<?php

namespace App\Support;

class PhoneNumberRedactor
{
    public static function redact(string $phoneNumber): string
    {
        $trimmed = trim($phoneNumber);

        if (strlen($trimmed) <= 5) {
            return '***';
        }

        return substr($trimmed, 0, 4).'***'.substr($trimmed, -3);
    }
}
