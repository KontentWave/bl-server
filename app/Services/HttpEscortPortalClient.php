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
    private int $renewalRetryAfter = 0;

    public function renewalRetryAfter(): int
    {
        return $this->renewalRetryAfter;
    }

    public function __construct(
        private readonly RotatingProxyConfig $rotatingProxyConfig,
        private readonly EscortAdUrlPolicy $adUrlPolicy,
    ) {}

    public function fetchAdHtml(string $adUrl): ?string
    {
        $this->renewalRetryAfter = 0;
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

            if (! $response || $response->successful()) {
                throw EscortPortalUnavailableException::forPhoneNumber($adUrl);
            }
        }

        $retryAfter = $response->header('Retry-After');
        if (is_string($retryAfter) && preg_match('/^[0-9]{1,10}$/D', $retryAfter) === 1) {
            $this->renewalRetryAfter = min(2147483647, (int) $retryAfter);
        } elseif (is_string($retryAfter)) {
            $date = \DateTimeImmutable::createFromFormat(DATE_RFC7231, $retryAfter);
            if ($date !== false && $date->format(DATE_RFC7231) === $retryAfter) {
                $this->renewalRetryAfter = min(2147483647, max(0, $date->getTimestamp() - now()->timestamp));
            }
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
