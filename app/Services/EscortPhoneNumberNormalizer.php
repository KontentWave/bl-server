<?php

namespace App\Services;

class EscortPhoneNumberNormalizer
{
    public function digitsOnly(string $phoneNumber): string
    {
        return preg_replace('/\D+/', '', $phoneNumber) ?? '';
    }

    public function normalizeE164(string $phoneNumber): ?string
    {
        $trimmedPhoneNumber = trim($phoneNumber);

        if ($trimmedPhoneNumber === '') {
            return null;
        }

        $hasExplicitPlus = str_starts_with($trimmedPhoneNumber, '+');
        $digits = $this->digitsOnly($trimmedPhoneNumber);

        if ($digits === '') {
            return null;
        }

        if ($hasExplicitPlus) {
            return '+'.$digits;
        }

        if (str_starts_with($digits, '00')) {
            return '+'.substr($digits, 2);
        }

        if (str_starts_with($digits, '421')) {
            return '+'.$digits;
        }

        if (str_starts_with($digits, '0')) {
            return '+421'.substr($digits, 1);
        }

        return null;
    }
}
