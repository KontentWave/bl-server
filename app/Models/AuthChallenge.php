<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

#[Fillable(['phone_number', 'password_hash', 'expires_at'])]
class AuthChallenge extends Model
{
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
        ];
    }

    public function hasValidPassword(string $password): bool
    {
        return ! $this->isExpired() && Hash::check($password, $this->password_hash);
    }

    public function isExpired(?Carbon $referenceTime = null): bool
    {
        $referenceTime ??= now();

        return $referenceTime->greaterThan($this->expires_at);
    }
}
