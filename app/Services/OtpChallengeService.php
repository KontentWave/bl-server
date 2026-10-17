<?php

namespace App\Services;

use App\Contracts\SmsSender;
use App\Exceptions\ApiDomainException;
use App\Exceptions\PhoneExtractionFailedException;
use App\Jobs\ExtractPhoneFromAdJob;
use App\Models\OtpChallenge;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class OtpChallengeService
{
    public function __construct(
        private readonly SmsSender $smsSender,
        private readonly EscortAdUrlPolicy $adUrlPolicy,
        private readonly OtpAbuseProtection $abuseProtection,
    ) {}

    public function issue(string $adUrl): array
    {
        $this->adUrlPolicy->validate($adUrl, config('services.escort_portal.driver') === 'fixture');

        $phoneNumber = app()->call([new ExtractPhoneFromAdJob($adUrl), 'handle']);

        if ($phoneNumber === null) {
            throw PhoneExtractionFailedException::create();
        }

        return $this->issueForPhone($phoneNumber, $adUrl);
    }

    public function issueForOwner(string $phoneNumber): array
    {
        if (! config('security.owner_enrollment_enabled')) {
            throw new ApiDomainException(
                apiCode: 'owner_enrollment_unavailable',
                message: 'Owner enrollment is unavailable.',
                status: 403,
                meta: ['retryable' => false],
            );
        }

        $ownerPhoneNumber = config('security.owner_phone_number');

        if (! is_string($ownerPhoneNumber) || preg_match('/^\+[1-9][0-9]{7,14}$/D', $ownerPhoneNumber) !== 1) {
            throw new ApiDomainException(
                apiCode: 'owner_enrollment_unavailable',
                message: 'Owner enrollment is unavailable.',
                status: 503,
                meta: ['retryable' => false],
                logLevel: 'error',
                logContext: ['reason' => 'owner_phone_configuration_invalid'],
            );
        }

        if (! hash_equals($ownerPhoneNumber, $phoneNumber)) {
            throw new ApiDomainException(
                apiCode: 'owner_enrollment_unavailable',
                message: 'Owner enrollment is unavailable.',
                status: 403,
                meta: ['retryable' => false],
            );
        }

        return $this->issueForPhone($ownerPhoneNumber, 'owner-enrollment');
    }

    private function issueForPhone(string $phoneNumber, string $adUrl): array
    {
        $this->abuseProtection->reserveSms($phoneNumber);
        $plainTextOtp = $this->generateOtp();

        $otpChallenge = DB::transaction(function () use ($phoneNumber, $adUrl, $plainTextOtp): OtpChallenge {
            $attributes = [
                'challenge_id' => (string) Str::uuid(),
                'ad_url' => $adUrl,
                'otp_hash' => Hash::make($plainTextOtp),
                'expires_at' => now()->addMinutes(15),
            ];
            $otpChallenge = OtpChallenge::query()
                ->where('phone_number', $phoneNumber)
                ->lockForUpdate()
                ->first();

            if ($otpChallenge) {
                $otpChallenge->update($attributes);

                return $otpChallenge;
            }

            return OtpChallenge::query()->create($attributes + ['phone_number' => $phoneNumber]);
        }, 3);

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
}
