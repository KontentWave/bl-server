<?php

namespace App\Services;

use App\Contracts\EscortPortalClient;
use App\Exceptions\EscortPortalTimeoutException;
use App\Exceptions\EscortPortalUnavailableException;
use App\Support\PhoneNumberRedactor;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class HttpEscortPortalClient implements EscortPortalClient
{
    public function __construct(
        private readonly EscortPhoneNumberNormalizer $escortPhoneNumberNormalizer,
    ) {
    }

    public function fetchAdHtml(string $phoneNumber): ?string
    {
        $baseUrl = config('services.escort_portal.base_url');
        $redactedPhoneNumber = PhoneNumberRedactor::redact($phoneNumber);

        if (! is_string($baseUrl) || $baseUrl === '') {
            throw EscortPortalUnavailableException::forPhoneNumber($phoneNumber);
        }

        $adPath = config('services.escort_portal.ad_path', '/search');
        $phoneQueryParameter = config('services.escort_portal.phone_query_parameter', 'phone');
        $timeout = (int) config('services.escort_portal.timeout', 10);
        $userAgent = (string) config('services.escort_portal.user_agent', 'BlacklistBackend/1.0');
        $proxy = config('services.escort_portal.proxy');

        $request = Http::withHeaders(['Accept' => 'text/html,application/xhtml+xml'])
            ->timeout($timeout)
            ->withUserAgent($userAgent);

        if (is_string($proxy) && $proxy !== '') {
            $request = $request->withOptions(['proxy' => $proxy]);
        }

        $url = rtrim($baseUrl, '/').'/'.ltrim((string) $adPath, '/');
        $normalizedPhoneNumber = $this->escortPhoneNumberNormalizer->normalize($phoneNumber);

        Log::info('escort_portal.fetch.started', [
            'phone_number' => $redactedPhoneNumber,
            'driver' => 'http',
            'url' => $url,
        ]);

        try {
            $response = $request->get($url, [
                (string) $phoneQueryParameter => $normalizedPhoneNumber,
            ]);
        } catch (ConnectionException) {
            throw EscortPortalTimeoutException::forPhoneNumber($phoneNumber);
        }

        if ($response->serverError()) {
            throw EscortPortalUnavailableException::forPhoneNumber($phoneNumber, $response->status());
        }

        if (! $response->successful()) {
            Log::info('escort_portal.fetch.no_match', [
                'phone_number' => $redactedPhoneNumber,
                'driver' => 'http',
                'status' => $response->status(),
            ]);

            return null;
        }

        Log::info('escort_portal.fetch.succeeded', [
            'phone_number' => $redactedPhoneNumber,
            'driver' => 'http',
            'status' => $response->status(),
        ]);

        return $response->body();
    }
}
