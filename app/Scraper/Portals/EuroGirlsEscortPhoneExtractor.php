<?php

namespace App\Scraper\Portals;

use App\Services\EscortPhoneNumberNormalizer;
use Symfony\Component\DomCrawler\Crawler;

class EuroGirlsEscortPhoneExtractor
{
    public function __construct(
        private readonly EscortPhoneNumberNormalizer $escortPhoneNumberNormalizer,
    ) {
    }

    public function supports(?string $host): bool
    {
        if ($host === null || $host === '') {
            return false;
        }

        return $host === 'www.eurogirlsescort.com' || $host === 'eurogirlsescort.com' || str_ends_with($host, '.eurogirlsescort.com');
    }

    public function extract(string $html): ?string
    {
        return $this->extractAll($html)[0] ?? null;
    }

    /**
     * @return list<string>
     */
    public function extractAll(string $html): array
    {
        $crawler = new Crawler($html);
        $results = [];

        $items = $crawler->filter('#js-phone .js-phone-item');

        if ($items->count() === 0) {
            return [];
        }

        foreach ($items as $index => $_node) {
            $itemCrawler = $items->eq($index);

            foreach ($this->extractCandidatesForItem($itemCrawler) as $phoneNumber) {
                if (! in_array($phoneNumber, $results, true)) {
                    $results[] = $phoneNumber;
                }
            }
        }

        return $results;
    }

    /**
     * @return list<string>
     */
    private function extractCandidatesForItem(Crawler $crawler): array
    {
        $results = [];

        foreach ([
            '.icon-telegram[data-telegram]' => 'data-telegram',
            '.icon-whatsapp[data-whatsapp]' => 'data-whatsapp',
            '.icon-viber[data-viber]' => 'data-viber',
            '.icon-phone[data-phone]' => 'data-phone',
        ] as $selector => $attribute) {
            foreach ($this->extractAttributeValues($crawler, $selector, $attribute) as $phoneNumber) {
                if (! in_array($phoneNumber, $results, true)) {
                    $results[] = $phoneNumber;
                }
            }
        }

        foreach ($this->extractEncodedPhones($crawler) as $phoneNumber) {
            if (! in_array($phoneNumber, $results, true)) {
                $results[] = $phoneNumber;
            }
        }

        return $results;
    }

    /**
     * @return list<string>
     */
    private function extractAttributeValues(Crawler $crawler, string $selector, string $attribute): array
    {
        $nodes = $crawler->filter($selector);
        $results = [];

        if ($nodes->count() === 0) {
            return [];
        }

        foreach ($nodes as $index => $_node) {
            $value = trim((string) ($nodes->eq($index)->attr($attribute) ?? ''));

            if ($value === '') {
                continue;
            }

            $normalized = $this->escortPhoneNumberNormalizer->normalizeE164($value);

            if ($normalized !== null) {
                $results[] = $normalized;
            }
        }

        return array_values(array_unique($results));
    }

    /**
     * @return list<string>
     */
    private function extractEncodedPhones(Crawler $crawler): array
    {
        $nodes = $crawler->filter('.js-phone[data-phone]');
        $results = [];

        if ($nodes->count() === 0) {
            return [];
        }

        foreach ($nodes as $index => $_node) {
            $encoded = trim((string) ($nodes->eq($index)->attr('data-phone') ?? ''));

            if ($encoded === '') {
                continue;
            }

            $decoded = strtr($encoded, [
                'A' => '+',
                'D' => '1',
                'C' => '2',
                'b' => '3',
                'X' => '4',
                '_' => '5',
                'l' => '6',
                'Q' => '7',
                'I' => '8',
                'L' => '9',
                '-' => '0',
            ]);

            $normalized = $this->escortPhoneNumberNormalizer->normalizeE164(str_replace('|', '', $decoded));

            if ($normalized !== null) {
                $results[] = $normalized;
            }
        }

        return array_values(array_unique($results));
    }
}
