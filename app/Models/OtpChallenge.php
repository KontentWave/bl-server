<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

#[Fillable(['challenge_id', 'ad_url', 'phone_number', 'otp_hash', 'expires_at'])]
class OtpChallenge extends Model
{
    protected $table = 'otp_challenges';

    protected static function booted(): void
    {
        static::creating(function (self $challenge): void {
            if (! $challenge->challenge_id) {
                $challenge->challenge_id = (string) Str::uuid();
            }
        });
    }

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
        ];
    }

    public function hasValidOtp(string $otp): bool
    {
        return ! $this->isExpired() && Hash::check($otp, $this->otp_hash);
    }

    public function isExpired(?Carbon $referenceTime = null): bool
    {
        $referenceTime ??= now();

        return $referenceTime->greaterThan($this->expires_at);
    }
}
