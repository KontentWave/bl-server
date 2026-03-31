<?php

namespace App\Services;

class EscortAdHtmlParser
{
    public function __construct(
        private readonly EscortPhoneNumberNormalizer $escortPhoneNumberNormalizer,
    ) {
    }

    public function containsPhoneNumber(string $html, string $phoneNumber): bool
    {
        $normalizedPhoneNumber = $this->escortPhoneNumberNormalizer->normalize($phoneNumber);

        if ($normalizedPhoneNumber === '') {
            return false;
        }

        $plainText = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        preg_match_all('/\+?[\d][\d\s().-]{7,}[\d]/', $plainText, $matches);

        foreach ($matches[0] ?? [] as $candidate) {
            if ($this->escortPhoneNumberNormalizer->normalize($candidate) === $normalizedPhoneNumber) {
                return true;
            }
        }

        return false;
    }
}
