<?php

namespace App\Services;

use App\Contracts\SmsSender;
use App\Exceptions\SmsDispatchFailedException;
use App\Support\PhoneNumberRedactor;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Log;

class SmstoolsSmsSender implements SmsSender
{
    public function __construct(
        private readonly HttpFactory $http,
    ) {}

    public function sendOtp(string $phoneNumber, string $otp): void
    {
        $apiKey = (string) config('services.sms.smstools.api_key');
        $endpoint = (string) config('services.sms.smstools.endpoint', 'https://api.smstools.sk/3/send_batch');
        $from = (string) config('services.sms.from', 'Blacklist');

        if (trim($apiKey) === '' || trim($from) === '') {
            throw SmsDispatchFailedException::create(
                message: 'The verification SMS provider is not configured.',
                context: ['provider' => 'smstools'],
            );
        }

        try {
            $response = $this->http
                ->connectTimeout(max(1, (int) config('services.sms.smstools.connect_timeout', 5)))
                ->timeout(max(1, (int) config('services.sms.smstools.timeout', 10)))
                ->acceptJson()
                ->asJson()
                ->post($endpoint, [
                    'auth' => [
                        'apikey' => $apiKey,
                    ],
                    'data' => [
                        'message' => sprintf('Your Blacklist verification code is %s', $otp),
                        'sender' => [
                            'text' => $from,
                        ],
                        'recipients' => [[
                            'phonenr' => $phoneNumber,
                        ]],
                    ],
                ]);
        } catch (ConnectionException) {
            throw SmsDispatchFailedException::create(context: [
                'provider' => 'smstools',
                'reason' => 'transport_error',
            ]);
        }

        $payload = $response->json();
        $resultId = is_array($payload) ? ($payload['id'] ?? null) : null;

        if (! $response->successful() || $resultId !== 'OK') {
            throw SmsDispatchFailedException::create(context: [
                'provider' => 'smstools',
                'http_status' => $response->status(),
                'reason' => 'provider_rejected_or_invalid_response',
            ]);
        }

        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
        $acceptedRecipients = is_array($data['recipients']['accepted'] ?? null)
            ? $data['recipients']['accepted']
            : [];
        $acceptedRecipient = null;

        foreach ($acceptedRecipients as $recipient) {
            if (is_array($recipient)
                && is_string($recipient['phonenr'] ?? null)
                && ltrim($recipient['phonenr'], '+') === ltrim($phoneNumber, '+')
                && $this->isValidIdentifier($recipient['msg_id'] ?? null)) {
                $acceptedRecipient = $recipient;
                break;
            }
        }

        if ($acceptedRecipient === null || ! $this->isValidIdentifier($data['batch_id'] ?? null)) {
            throw SmsDispatchFailedException::create(context: [
                'provider' => 'smstools',
                'http_status' => $response->status(),
                'reason' => 'recipient_acceptance_unconfirmed',
            ]);
        }

        Log::info('sms.otp_dispatched', [
            'provider' => 'smstools',
            'phone_number' => PhoneNumberRedactor::redact($phoneNumber),
            'batch_id' => $data['batch_id'],
            'message_id' => $acceptedRecipient['msg_id'],
        ]);
    }

    private function isValidIdentifier(mixed $identifier): bool
    {
        return (is_int($identifier) && $identifier > 0)
            || (is_string($identifier) && preg_match('/^[1-9][0-9]*$/D', $identifier) === 1);
    }
}
