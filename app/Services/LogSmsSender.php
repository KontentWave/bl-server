<?php

namespace App\Services;

use App\Contracts\SmsSender;
use App\Support\PhoneNumberRedactor;
use Illuminate\Support\Facades\Log;

class LogSmsSender implements SmsSender
{
    public function sendOtp(string $phoneNumber, string $otp): void
    {
        $context = [
            'phone_number' => PhoneNumberRedactor::redact($phoneNumber),
        ];

        if (! app()->environment('production') && config('services.sms.log_otp_in_non_production', true)) {
            $context['otp'] = $otp;
        }

        Log::info('sms.otp_dispatched', $context);
    }
}
