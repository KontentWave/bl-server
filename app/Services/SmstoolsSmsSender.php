<?php

namespace App\Services;

use App\Contracts\SmsSender;
use App\Exceptions\SmsDispatchFailedException;
use App\Support\PhoneNumberRedactor;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Log;

class SmstoolsSmsSender implements SmsSender
{
    public function __construct(
        private readonly HttpFactory $http,
    ) {
    }

    public function sendOtp(string $phoneNumber, string $otp): void
    {
        $apiKey = (string) config('services.sms.smstools.api_key');
        $endpoint = (string) config('services.sms.smstools.endpoint', 'https://api.smstools.sk/3/send_batch');
        $from = (string) config('services.sms.from', 'Blacklist');

        if ($apiKey === '') {
            throw SmsDispatchFailedException::create(
                message: 'The verification SMS provider is not configured.',
                context: ['provider' => 'smstools'],
            );
        }

        $response = $this->http
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

        $payload = $response->json();
        $resultId = is_array($payload) ? ($payload['id'] ?? null) : null;

        if (! $response->successful() || $resultId !== 'OK') {
            throw SmsDispatchFailedException::create(context: $this->failureContext(
                response: $response,
                phoneNumber: $phoneNumber,
                resultId: is_scalar($resultId) ? (string) $resultId : null,
                note: is_array($payload) && isset($payload['note']) && is_scalar($payload['note']) ? (string) $payload['note'] : null,
            ));
        }

        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
        $acceptedRecipients = is_array($data['recipients']['accepted'] ?? null)
            ? $data['recipients']['accepted']
            : [];
        $firstAcceptedRecipient = is_array($acceptedRecipients[0] ?? null) ? $acceptedRecipients[0] : [];

        Log::info('sms.otp_dispatched', [
            'provider' => 'smstools',
            'phone_number' => PhoneNumberRedactor::redact($phoneNumber),
            'batch_id' => $data['batch_id'] ?? null,
            'message_id' => $firstAcceptedRecipient['msg_id'] ?? null,
        ]);
    }

    private function failureContext(
        Response $response,
        string $phoneNumber,
        ?string $resultId,
        ?string $note,
    ): array {
        return array_filter([
            'provider' => 'smstools',
            'phone_number' => PhoneNumberRedactor::redact($phoneNumber),
            'http_status' => $response->status(),
            'provider_code' => $resultId,
            'provider_note' => $note,
        ], static fn (mixed $value): bool => $value !== null && $value !== '');
    }
}
