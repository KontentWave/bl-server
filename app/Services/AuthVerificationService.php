<?php

namespace App\Services;

use App\Exceptions\ChallengeNotFoundException;
use App\Exceptions\OtpInvalidOrExpiredException;
use App\Exceptions\SignatureInvalidException;
use App\Models\DeviceBinding;
use App\Models\OtpChallenge;
use App\Support\PhoneNumberRedactor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AuthVerificationService
{
    public function __construct(
        private readonly DeviceSignatureService $deviceSignatureService,
        private readonly OtpAbuseProtection $abuseProtection,
    ) {}

    public function verify(string $challengeId, string $otp, string $publicKey, string $signature): DeviceBinding
    {
        Log::info('auth.verify.attempted', [
            'challenge_id' => $challengeId,
        ]);

        $otpChallenge = OtpChallenge::query()->where('challenge_id', $challengeId)->first();

        if (! $otpChallenge) {
            throw ChallengeNotFoundException::create();
        }

        // Database-backed attempt budgets must survive a rejected or rolled-back verification.
        $this->abuseProtection->recordVerificationAttempt($otpChallenge);
        $phoneNumber = $otpChallenge->phone_number;

        [$deviceBinding, $redactedPhoneNumber] = DB::transaction(function () use (
            $phoneNumber,
            $challengeId,
            $otp,
            $publicKey,
            $signature,
        ): array {
            $otpChallenge = OtpChallenge::query()
                ->where('phone_number', $phoneNumber)
                ->lockForUpdate()
                ->first();

            if (! $otpChallenge || $otpChallenge->challenge_id !== $challengeId) {
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

            $normalizedPublicKey = $this->deviceSignatureService->normalizePublicKey($publicKey);
            $signatureDiagnostics = $this->deviceSignatureService->verificationDiagnostics(
                $challengeId,
                $normalizedPublicKey,
                $signature,
            );

            if (! $signatureDiagnostics['verified']) {
                $logContext = [
                    'challenge_id' => $challengeId,
                    'phone_number' => $redactedPhoneNumber,
                ];

                if (! app()->environment('production')) {
                    $logContext = array_merge($logContext, $signatureDiagnostics);
                }

                Log::warning('auth.verify.rejected.invalid_signature', $logContext);

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

            return [$deviceBinding, $redactedPhoneNumber];
        }, 3);

        Log::info('auth.verify.bound', [
            'challenge_id' => $challengeId,
            'phone_number' => $redactedPhoneNumber,
            'device_binding_id' => $deviceBinding->id,
        ]);

        return $deviceBinding->fresh();
    }
}
