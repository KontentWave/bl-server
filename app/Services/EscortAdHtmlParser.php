<?php

namespace App\Services;

use App\Scraper\Portals\AmaterkySkPhoneExtractor;

class EscortAdHtmlParser
{
    public function __construct(
        private readonly EscortPhoneNumberNormalizer $escortPhoneNumberNormalizer,
        private readonly AmaterkySkPhoneExtractor $amaterkySkPhoneExtractor,
    ) {
    }

    public function extractPrimaryPhoneNumber(string $html, ?string $adUrl = null): ?string
    {
        $host = is_string($adUrl) ? parse_url($adUrl, PHP_URL_HOST) : null;

        if (is_string($host) && $this->amaterkySkPhoneExtractor->supports($host)) {
            $phoneNumber = $this->amaterkySkPhoneExtractor->extract($html);

            if ($phoneNumber !== null) {
                return $phoneNumber;
            }
        }

        $plainText = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        preg_match_all('/\+?[\d][\d\s().-]{7,}[\d]/', $plainText, $matches);

        foreach ($matches[0] ?? [] as $candidate) {
            $normalizedCandidate = $this->escortPhoneNumberNormalizer->normalizeE164($candidate);

            if ($normalizedCandidate !== null) {
                return $normalizedCandidate;
            }
        }

        return null;
    }
}
