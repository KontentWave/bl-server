<?php

namespace App\Services;

use App\Contracts\EscortPortalClient;
use App\Exceptions\EscortPortalTimeoutException;
use App\Exceptions\EscortPortalUnavailableException;
use App\Scraper\Proxy\RotatingProxyConfig;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class HttpEscortPortalClient implements EscortPortalClient
{
    public function __construct(
        private readonly RotatingProxyConfig $rotatingProxyConfig,
        private readonly EscortAdUrlPolicy $adUrlPolicy,
    ) {}

    public function fetchAdHtml(string $adUrl): ?string
    {
        $host = $this->adUrlPolicy->validate($adUrl);

        if (! defined('CURLOPT_CONNECT_TO')) {
            throw EscortPortalUnavailableException::forPhoneNumber($host);
        }

        $address = $this->adUrlPolicy->resolvePublicAddress($host);
        $connectHost = str_contains($address, ':') ? '['.$address.']' : $address;

        $timeout = (int) config('services.escort_portal.timeout', 10);
        $userAgent = (string) config('services.escort_portal.user_agent', 'BlacklistBackend/1.0');
        $proxy = config('services.escort_portal.proxy');

        $request = Http::withHeaders(['Accept' => 'text/html,application/xhtml+xml'])
            ->withoutRedirecting()
            ->withOptions([
                'proxy' => '',
                'verify' => true,
                'curl' => [CURLOPT_CONNECT_TO => [$host.':443:'.$connectHost.':443']],
            ])
            ->timeout($timeout)
            ->withUserAgent($userAgent);

        if ($this->rotatingProxyConfig->isEnabled()) {
            $request = $request
                ->connectTimeout($this->rotatingProxyConfig->connectTimeout())
                ->withOptions(['proxy' => $this->rotatingProxyConfig->proxyUrl()]);
        } elseif (is_string($proxy) && $proxy !== '') {
            $request = $request->withOptions(['proxy' => $proxy]);
        }

        Log::info('escort_portal.fetch.started', [
            'ad_url_hash' => sha1($adUrl),
            'driver' => 'http',
        ]);

        try {
            $response = $request->get($adUrl);
        } catch (ConnectionException) {
            throw EscortPortalTimeoutException::forPhoneNumber($adUrl);
        } catch (RequestException $exception) {
            $response = $exception->response;

            if ($response?->serverError()) {
                throw EscortPortalUnavailableException::forPhoneNumber($adUrl, $response->status());
            }

            if ($response && ! $response->successful()) {
                Log::info('escort_portal.fetch.no_match', [
                    'ad_url_hash' => sha1($adUrl),
                    'driver' => 'http',
                    'status' => $response->status(),
                ]);

                return null;
            }

            throw EscortPortalUnavailableException::forPhoneNumber($adUrl);
        }

        if ($response->serverError()) {
            throw EscortPortalUnavailableException::forPhoneNumber($adUrl, $response->status());
        }

        if (! $response->successful()) {
            Log::info('escort_portal.fetch.no_match', [
                'ad_url_hash' => sha1($adUrl),
                'driver' => 'http',
                'status' => $response->status(),
            ]);

            return null;
        }

        Log::info('escort_portal.fetch.succeeded', [
            'ad_url_hash' => sha1($adUrl),
            'driver' => 'http',
            'status' => $response->status(),
        ]);

        return $response->body();
    }
}
