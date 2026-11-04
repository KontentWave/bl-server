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
use Illuminate\Support\Str;

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

        $normalizedPublicKey = $this->deviceSignatureService->normalizePublicKey($publicKey);
        [$deviceBinding, $redactedPhoneNumber] = app(BindingLifecycle::class)->withSelection($normalizedPublicKey, function ($current, $bindings, $keyHash) use (
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

            $deviceBinding = DeviceBinding::query()->where('phone_number', $otpChallenge->phone_number)->lockForUpdate()->first();
            $version = max($deviceBinding?->lifecycle_version ?? 0, $bindings->max('lifecycle_version') ?? 0) + 1;
            foreach ($bindings as $prior) {
                if ($prior->id !== $deviceBinding?->id && $prior->lifecycle_state !== 'superseded') {
                    $prior->fill([
                        'lifecycle_state' => 'superseded',
                        'lifecycle_version' => ($prior->lifecycle_version ?? 0) + 1,
                        'next_renewal_at' => null,
                        'offboarding_reason' => 'superseded',
                    ])->save();
                }
            }
            $deviceBinding = DeviceBinding::query()->updateOrCreate(
                ['phone_number' => $otpChallenge->phone_number],
                [
                    'public_key' => $normalizedPublicKey,
                    'verified_at' => $verificationTime = now(),
                    'enrollment_id' => (string) Str::uuid(),
                    'enrollment_kind' => $otpChallenge->ad_url === 'owner-enrollment' ? 'phone_only' : 'ad',
                    'ad_url' => $otpChallenge->ad_url === 'owner-enrollment' ? null : $otpChallenge->ad_url,
                    'lifecycle_state' => 'active',
                    'lifecycle_version' => $version,
                    'last_renewed_at' => null,
                    'next_renewal_at' => $otpChallenge->ad_url === 'owner-enrollment' ? null : $verificationTime->copy()->addSeconds(86400),
                    'offboarded_at' => null,
                    'offboarding_reason' => null,
                    'fetch_window_at' => null,
                    'fetch_count' => 0,
                    'renewal_failures' => 0,
                    'retry_at' => null,
                ],
            );
            DB::table('device_key_selections')->where('key_hash', $keyHash)->update(['binding_id' => $deviceBinding->id]);
            app(BindingLifecycle::class)->recordGeneration($deviceBinding);

            $otpChallenge->delete();

            return [$deviceBinding, $redactedPhoneNumber];
        });

        Log::info('auth.verify.bound', [
            'challenge_id' => $challengeId,
            'phone_number' => $redactedPhoneNumber,
            'device_binding_id' => $deviceBinding->id,
        ]);

        return $deviceBinding;
    }
}
