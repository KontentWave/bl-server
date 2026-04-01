<?php

namespace App\Jobs;

use App\Contracts\EscortPortalClient;
use App\Services\EscortAdHtmlParser;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ExtractPhoneFromAdJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $adUrl,
    ) {
    }

    public function handle(EscortPortalClient $escortPortalClient, EscortAdHtmlParser $escortAdHtmlParser): ?string
    {
        Log::info('escort_ad_extraction.started', [
            'ad_url_hash' => sha1($this->adUrl),
        ]);

        $html = $escortPortalClient->fetchAdHtml($this->adUrl);

        if ($html === null) {
            Log::info('escort_ad_extraction.no_html', [
                'ad_url_hash' => sha1($this->adUrl),
            ]);

            return null;
        }

        $phoneNumber = $escortAdHtmlParser->extractPrimaryPhoneNumber($html);

        if ($phoneNumber === null) {
            Log::info('escort_ad_extraction.phone_missing', [
                'ad_url_hash' => sha1($this->adUrl),
            ]);

            return null;
        }

        $overridePhoneNumber = config('services.escort_portal.development_phone_override');

        if (! app()->environment('production') && is_string($overridePhoneNumber) && $overridePhoneNumber !== '') {
            Log::info('escort_ad_extraction.override_applied', [
                'ad_url_hash' => sha1($this->adUrl),
            ]);

            return $overridePhoneNumber;
        }

        Log::info('escort_ad_extraction.completed', [
            'ad_url_hash' => sha1($this->adUrl),
        ]);

        return $phoneNumber;
    }
}
