<?php

namespace App\Services;

class EscortAdHtmlParser
{
    public function __construct(
        private readonly EscortPhoneNumberNormalizer $escortPhoneNumberNormalizer,
    ) {
    }

    public function extractPrimaryPhoneNumber(string $html): ?string
    {
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
