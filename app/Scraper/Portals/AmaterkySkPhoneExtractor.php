<?php

namespace App\Scraper\Portals;

use App\Exceptions\AdTemporarilyDisabledException;
use App\Services\EscortPhoneNumberNormalizer;
use Symfony\Component\DomCrawler\Crawler;

class AmaterkySkPhoneExtractor
{
    public function __construct(
        private readonly EscortPhoneNumberNormalizer $escortPhoneNumberNormalizer,
    ) {}

    public function supports(?string $host): bool
    {
        if ($host === null || $host === '') {
            return false;
        }

        return $host === 'amaterky.sk' || str_ends_with($host, '.amaterky.sk');
    }

    public function extract(string $html): ?string
    {
        $crawler = new Crawler($html);

        $this->ensureAdIsNotTemporarilyDisabled($crawler);

        foreach ([
            'a.detail-floater-link-phone[href^="tel:"]' => 'tel:',
            'a.detail-floater-link-sms[href^="sms:"]' => 'sms:',
        ] as $selector => $schemePrefix) {
            $phoneNumber = $this->extractFromLink($crawler, $selector, $schemePrefix);

            if ($phoneNumber !== null) {
                return $phoneNumber;
            }
        }

        return $this->extractFromHeading($crawler);
    }

    public function extractAll(string $html): array
    {
        $crawler = new Crawler($html);
        $this->ensureAdIsNotTemporarilyDisabled($crawler);
        $results = [];
        foreach ([
            'a.detail-floater-link-phone[href^="tel:"]' => 'tel:',
            'a.detail-floater-link-sms[href^="sms:"]' => 'sms:',
        ] as $selector => $prefix) {
            foreach ($crawler->filter($selector) as $node) {
                $value = strtok(substr($node->getAttribute('href'), strlen($prefix)), '?');
                $phone = $value === false ? null : $this->escortPhoneNumberNormalizer->normalizeE164($value);
                if ($phone !== null) {
                    $results[] = $phone;
                }
            }
        }
        foreach ($crawler->filter('.card.card-contact h2') as $node) {
            $phone = $this->escortPhoneNumberNormalizer->normalizeE164($node->textContent);
            if ($phone !== null) {
                $results[] = $phone;
            }
        }

        return array_values(array_unique($results));
    }

    private function ensureAdIsNotTemporarilyDisabled(Crawler $crawler): void
    {
        $nodes = $crawler->filter('h2.alert-heading');

        if ($nodes->count() === 0) {
            return;
        }

        foreach ($nodes as $index => $_node) {
            $heading = trim($nodes->eq($index)->text(''));

            if ($heading === 'Vypnuty zadavatelom' || $heading === 'Vypnutý zadávateľom') {
                throw AdTemporarilyDisabledException::create();
            }
        }
    }

    private function extractFromLink(Crawler $crawler, string $selector, string $schemePrefix): ?string
    {
        $nodes = $crawler->filter($selector);

        if ($nodes->count() === 0) {
            return null;
        }

        foreach ($nodes as $index => $_node) {
            $href = (string) ($nodes->eq($index)->attr('href') ?? '');

            if ($href === '') {
                continue;
            }

            $value = substr($href, strlen($schemePrefix));
            $value = strtok($value, '?');

            if ($value === false || $value === '') {
                continue;
            }

            $normalized = $this->escortPhoneNumberNormalizer->normalizeE164($value);

            if ($normalized !== null) {
                return $normalized;
            }
        }

        return null;
    }

    private function extractFromHeading(Crawler $crawler): ?string
    {
        $nodes = $crawler->filter('.card.card-contact h2');

        if ($nodes->count() === 0) {
            return null;
        }

        foreach ($nodes as $index => $_node) {
            $normalized = $this->escortPhoneNumberNormalizer->normalizeE164($nodes->eq($index)->text(''));

            if ($normalized !== null) {
                return $normalized;
            }
        }

        return null;
    }
}
