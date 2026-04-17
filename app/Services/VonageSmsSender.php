<?php

namespace App\Services;

use App\Contracts\SmsSender;
use App\Exceptions\SmsDispatchFailedException;
use App\Support\PhoneNumberRedactor;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;
use Vonage\Client;
use Vonage\Client\Credentials\Basic;
use Vonage\SMS\Message\SMS;

class VonageSmsSender implements SmsSender
{
    public function sendOtp(string $phoneNumber, string $otp): void
    {
        $from = (string) config('services.sms.from', 'Blacklist');
        $key = (string) config('services.sms.vonage.key');
        $secret = (string) config('services.sms.vonage.secret');

        if ($key === '' || $secret === '') {
            throw SmsDispatchFailedException::create(
                message: 'The verification SMS provider is not configured.',
                context: ['provider' => 'vonage'],
            );
        }

        $message = new SMS(
            to: $phoneNumber,
            from: $from,
            message: sprintf('Your Blacklist verification code is %s', $otp),
        );

        try {
            $result = $this->makeClient($key, $secret)->sms()->send($message);
            $sentMessage = $result->current();

            Log::info('sms.otp_dispatched', [
                'provider' => 'vonage',
                'phone_number' => PhoneNumberRedactor::redact($phoneNumber),
                'message_id' => $sentMessage->getMessageId(),
                'remaining_balance' => $sentMessage->getRemainingBalance(),
            ]);
        } catch (Throwable $exception) {
            $context = [
                'provider' => 'vonage',
                'phone_number' => PhoneNumberRedactor::redact($phoneNumber),
                'exception' => $exception::class,
            ];

            if ($exception instanceof RuntimeException || method_exists($exception, 'getCode')) {
                $context['provider_code'] = $exception->getCode();
            }

            throw SmsDispatchFailedException::create(context: $context);
        }
    }

    protected function makeClient(string $key, string $secret): Client
    {
        return new Client(new Basic($key, $secret));
    }
}
