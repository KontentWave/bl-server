<?php

namespace App\Services;

use App\Exceptions\EscortPortalUnavailableException;
use App\Exceptions\InvalidAdUrlException;
use Symfony\Component\HttpFoundation\IpUtils;

class EscortAdUrlPolicy
{
    public function validate(string $adUrl, bool $allowFixture = false): string
    {
        $parts = parse_url($adUrl);
        $host = is_array($parts) ? strtolower($parts['host'] ?? '') : '';
        $hosts = ['amaterky.sk', 'www.amaterky.sk', 'eurogirlsescort.com', 'www.eurogirlsescort.com'];

        if ($allowFixture && app()->environment(['local', 'testing'])) {
            $hosts[] = 'portal.example.test';
        }

        if (! filter_var($adUrl, FILTER_VALIDATE_URL)
            || ! is_array($parts)
            || strtolower($parts['scheme'] ?? '') !== 'https'
            || isset($parts['user'])
            || isset($parts['pass'])
            || (isset($parts['port']) && $parts['port'] !== 443)
            || ! in_array($host, $hosts, true)) {
            throw InvalidAdUrlException::create();
        }

        return $host;
    }

    public function resolvePublicAddress(string $host): string
    {
        $addresses = $this->lookupAddresses($host);

        if ($addresses === []) {
            throw EscortPortalUnavailableException::forPhoneNumber($host);
        }

        foreach ($addresses as $address) {
            if (! filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)
                || IpUtils::checkIp($address, [
                    '0.0.0.0/8', '100.64.0.0/10', '192.0.0.0/24', '192.0.2.0/24', '192.88.99.0/24',
                    '198.18.0.0/15', '198.51.100.0/24', '203.0.113.0/24', '224.0.0.0/4', '240.0.0.0/4',
                    '2001::/23', '2001:db8::/32', '2002::/16', '3fff::/20',
                ])
                || (str_contains($address, ':') && ! IpUtils::checkIp($address, '2000::/3'))) {
                throw InvalidAdUrlException::create();
            }
        }

        return $addresses[0];
    }

    protected function lookupAddresses(string $host): array
    {
        $records = @dns_get_record($host, DNS_A | DNS_AAAA);
        $addresses = [];

        foreach (is_array($records) ? $records : [] as $record) {
            $address = $record['ip'] ?? $record['ipv6'] ?? null;

            if (is_string($address)) {
                $addresses[] = $address;
            }
        }

        return array_values(array_unique($addresses));
    }
}
