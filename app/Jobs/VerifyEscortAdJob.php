<?php

namespace App\Jobs;

use App\Contracts\EscortPortalClient;
use App\Services\EscortAdHtmlParser;
use App\Support\PhoneNumberRedactor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class VerifyEscortAdJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $phoneNumber,
    ) {
    }

    public function handle(EscortPortalClient $escortPortalClient, EscortAdHtmlParser $escortAdHtmlParser): bool
    {
        Log::info('escort_ad_verification.started', [
            'phone_number' => PhoneNumberRedactor::redact($this->phoneNumber),
        ]);

        $html = $escortPortalClient->fetchAdHtml($this->phoneNumber);

        if ($html === null) {
            Log::info('escort_ad_verification.not_found', [
                'phone_number' => PhoneNumberRedactor::redact($this->phoneNumber),
            ]);

            return false;
        }

        $result = $escortAdHtmlParser->containsPhoneNumber($html, $this->phoneNumber);

        Log::info('escort_ad_verification.completed', [
            'phone_number' => PhoneNumberRedactor::redact($this->phoneNumber),
            'verified' => $result,
        ]);

        return $result;
    }
}
