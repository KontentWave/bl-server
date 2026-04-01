<?php

namespace App\Services;

use App\Exceptions\ChallengeNotFoundException;
use App\Exceptions\OtpInvalidOrExpiredException;
use App\Exceptions\SignatureInvalidException;
use App\Models\DeviceBinding;
use App\Models\OtpChallenge;
use App\Support\PhoneNumberRedactor;
use Illuminate\Support\Facades\Log;

class AuthVerificationService
{
    public function __construct(
        private readonly DeviceSignatureService $deviceSignatureService,
    ) {
    }

    public function verify(string $challengeId, string $otp, string $publicKey, string $signature): DeviceBinding
    {
        $normalizedPublicKey = $this->deviceSignatureService->normalizePublicKey($publicKey);
        Log::info('auth.verify.attempted', [
            'challenge_id' => $challengeId,
        ]);

        $otpChallenge = OtpChallenge::query()
            ->where('challenge_id', $challengeId)
            ->first();

        if (! $otpChallenge) {
            throw ChallengeNotFoundException::create();
        }

        $redactedPhoneNumber = PhoneNumberRedactor::redact($otpChallenge->phone_number);

        if (! $otpChallenge->hasValidOtp($otp)) {
            Log::warning('auth.verify.rejected.invalid_otp', [
                'challenge_id' => $challengeId,
                'phone_number' => $redactedPhoneNumber,
            ]);

            throw OtpInvalidOrExpiredException::create();
        }

        if (! $this->deviceSignatureService->verify($challengeId, $normalizedPublicKey, $signature)) {
            Log::warning('auth.verify.rejected.invalid_signature', [
                'challenge_id' => $challengeId,
                'phone_number' => $redactedPhoneNumber,
            ]);

            throw SignatureInvalidException::create();
        }

        $deviceBinding = DeviceBinding::query()->updateOrCreate(
            ['phone_number' => $otpChallenge->phone_number],
            [
                'public_key' => $normalizedPublicKey,
                'verified_at' => now(),
            ],
        );

        $otpChallenge->delete();

        Log::info('auth.verify.bound', [
            'challenge_id' => $challengeId,
            'phone_number' => $redactedPhoneNumber,
            'device_binding_id' => $deviceBinding->id,
        ]);

        return $deviceBinding->fresh();
    }
}
