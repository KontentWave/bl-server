<?php

namespace App\Services;

use App\Scraper\Portals\AmaterkySkPhoneExtractor;
use App\Scraper\Portals\EuroGirlsEscortPhoneExtractor;

class EscortAdHtmlParser
{
    public function __construct(
        private readonly EscortPhoneNumberNormalizer $escortPhoneNumberNormalizer,
        private readonly AmaterkySkPhoneExtractor $amaterkySkPhoneExtractor,
        private readonly EuroGirlsEscortPhoneExtractor $euroGirlsEscortPhoneExtractor,
    ) {
    }

    public function extractPrimaryPhoneNumber(string $html, ?string $adUrl = null): ?string
    {
        return $this->extractPhoneNumbers($html, $adUrl)[0] ?? null;
    }

    /**
     * @return list<string>
     */
    public function extractPhoneNumbers(string $html, ?string $adUrl = null): array
    {
        $host = is_string($adUrl) ? parse_url($adUrl, PHP_URL_HOST) : null;

        if (is_string($host) && $this->amaterkySkPhoneExtractor->supports($host)) {
            $phoneNumber = $this->amaterkySkPhoneExtractor->extract($html);

            if ($phoneNumber !== null) {
                return [$phoneNumber];
            }
        }

        if (is_string($host) && $this->euroGirlsEscortPhoneExtractor->supports($host)) {
            $phoneNumbers = $this->euroGirlsEscortPhoneExtractor->extractAll($html);

            if ($phoneNumbers !== []) {
                return $phoneNumbers;
            }
        }

        $plainText = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        preg_match_all('/\+?[\d][\d\s().-]{7,}[\d]/', $plainText, $matches);

        $results = [];

        foreach ($matches[0] ?? [] as $candidate) {
            $normalizedCandidate = $this->escortPhoneNumberNormalizer->normalizeE164($candidate);

            if ($normalizedCandidate !== null) {
                if (! in_array($normalizedCandidate, $results, true)) {
                    $results[] = $normalizedCandidate;
                }
            }
        }

        return $results;
    }
}
