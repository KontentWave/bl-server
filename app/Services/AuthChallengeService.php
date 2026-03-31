<?php

namespace App\Services;

use App\Models\AuthChallenge;
use Illuminate\Support\Facades\Hash;

class AuthChallengeService
{
    public function issue(string $phoneNumber): array
    {
        $plainTextPassword = $this->generatePassword();

        $authChallenge = AuthChallenge::query()->updateOrCreate(
            ['phone_number' => $phoneNumber],
            [
                'password_hash' => Hash::make($plainTextPassword),
                'expires_at' => now()->addHour(),
            ],
        );

        return [$authChallenge->fresh(), $plainTextPassword];
    }

    private function generatePassword(int $length = 10): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $alphabetLength = strlen($alphabet) - 1;
        $password = '';

        for ($index = 0; $index < $length; $index++) {
            $password .= $alphabet[random_int(0, $alphabetLength)];
        }

        return $password;
    }
}
