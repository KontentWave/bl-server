<?php

namespace App\Services;

use App\Contracts\SmsSender;
use App\Exceptions\InvalidAdUrlException;
use App\Exceptions\PhoneExtractionFailedException;
use App\Jobs\ExtractPhoneFromAdJob;
use App\Models\OtpChallenge;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class OtpChallengeService
{
    public function __construct(
        private readonly SmsSender $smsSender,
    ) {
    }

    public function issue(string $adUrl): array
    {
        if (! $this->isValidAdUrl($adUrl)) {
            throw InvalidAdUrlException::create();
        }

        $phoneNumber = app()->call([new ExtractPhoneFromAdJob($adUrl), 'handle']);

        if ($phoneNumber === null) {
            throw PhoneExtractionFailedException::create();
        }

        $plainTextOtp = $this->generateOtp();

        $otpChallenge = OtpChallenge::query()->updateOrCreate(
            ['phone_number' => $phoneNumber],
            [
                'challenge_id' => (string) Str::uuid(),
                'ad_url' => $adUrl,
                'otp_hash' => Hash::make($plainTextOtp),
                'expires_at' => now()->addMinutes(15),
            ],
        );

        $this->smsSender->sendOtp($phoneNumber, $plainTextOtp);

        Log::info('auth.initiate.sms_challenge_created', [
            'challenge_id' => $otpChallenge->challenge_id,
        ]);

        return [$otpChallenge->fresh(), $plainTextOtp];
    }

    private function generateOtp(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    private function isValidAdUrl(string $adUrl): bool
    {
        if (! filter_var($adUrl, FILTER_VALIDATE_URL)) {
            return false;
        }

        $scheme = parse_url($adUrl, PHP_URL_SCHEME);

        return in_array($scheme, ['http', 'https'], true);
    }
}
