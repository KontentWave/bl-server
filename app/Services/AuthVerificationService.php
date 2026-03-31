<?php

namespace App\Services;

use App\Contracts\EscortAdVerifier;
use App\Exceptions\EscortAdNotVerifiedException;
use App\Models\AuthChallenge;
use App\Models\DeviceBinding;
use App\Support\PhoneNumberRedactor;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class AuthVerificationService
{
    public function __construct(
        private readonly EscortAdVerifier $escortAdVerifier,
        private readonly DeviceSignatureService $deviceSignatureService,
    ) {
    }

    public function verify(string $phoneNumber, string $password, string $publicKey, string $signature): DeviceBinding
    {
        $normalizedPublicKey = $this->deviceSignatureService->normalizePublicKey($publicKey);
        $redactedPhoneNumber = PhoneNumberRedactor::redact($phoneNumber);

        Log::info('auth.verify.attempted', [
            'phone_number' => $redactedPhoneNumber,
        ]);

        $authChallenge = AuthChallenge::query()
            ->where('phone_number', $phoneNumber)
            ->first();

        if (! $authChallenge || ! $authChallenge->hasValidPassword($password)) {
            Log::warning('auth.verify.rejected.invalid_password', [
                'phone_number' => $redactedPhoneNumber,
            ]);

            throw ValidationException::withMessages([
                'password' => ['The provided password is invalid or expired.'],
            ]);
        }

        if (! $this->deviceSignatureService->verify($phoneNumber, $normalizedPublicKey, $signature)) {
            Log::warning('auth.verify.rejected.invalid_signature', [
                'phone_number' => $redactedPhoneNumber,
            ]);

            throw ValidationException::withMessages([
                'signature' => ['The provided device signature is invalid.'],
            ]);
        }

        if (! $this->escortAdVerifier->hasActiveAdForPhoneNumber($phoneNumber)) {
            throw EscortAdNotVerifiedException::forPhoneNumber($phoneNumber);
        }

        $deviceBinding = DeviceBinding::query()->updateOrCreate(
            ['phone_number' => $phoneNumber],
            [
                'public_key' => $normalizedPublicKey,
                'verified_at' => now(),
            ],
        );

        $authChallenge->delete();

        Log::info('auth.verify.bound', [
            'phone_number' => $redactedPhoneNumber,
            'device_binding_id' => $deviceBinding->id,
        ]);

        return $deviceBinding->fresh();
    }
}
