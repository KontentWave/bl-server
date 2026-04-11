<?php

namespace App\Scraper\Proxy;

use App\Models\ScraperProxyAttempt;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

class ProxyProbeService
{
    public function __construct(
        private readonly RotatingProxyConfig $proxyConfig,
    ) {
    }

    public function isConfigured(): bool
    {
        return $this->proxyConfig->isEnabled();
    }

    public function probe(?string $targetUrl = null, string $method = 'GET'): ScraperProxyAttempt
    {
        $url = $targetUrl ?? (string) config('scraping.probe.url');

        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            throw new \InvalidArgumentException('The proxy probe URL must be a valid URL.');
        }

        if (! $this->isConfigured()) {
            throw new \RuntimeException('The rotating proxy endpoint is not configured.');
        }

        $startedAt = microtime(true);
        $request = Http::withHeaders(['Accept' => 'text/html,application/xhtml+xml'])
            ->connectTimeout($this->proxyConfig->connectTimeout())
            ->timeout($this->proxyConfig->timeout())
            ->withUserAgent((string) config('services.escort_portal.user_agent', 'BlacklistBackend/1.0'))
            ->withOptions(['proxy' => $this->proxyConfig->proxyUrl()]);

        try {
            $response = $request->send($method, $url);
        } catch (ConnectionException $exception) {
            return $this->recordAttempt(
                url: $url,
                method: $method,
                latencyMs: $this->latencyMs($startedAt),
                outcome: $this->classifyConnectionException($exception),
                errorType: $exception::class,
                errorMessage: $exception->getMessage(),
            );
        } catch (RequestException $exception) {
            $response = $exception->response;
            $status = $response?->status();

            return $this->recordAttempt(
                url: $url,
                method: $method,
                latencyMs: $this->latencyMs($startedAt),
                outcome: $status !== null ? $this->classifyHttpStatus($status) : 'http_error',
                httpStatus: $status,
                errorType: $exception::class,
                errorMessage: $exception->getMessage(),
                responsePreview: $response ? $this->responsePreview($response->body()) : null,
            );
        }

        $status = $response->status();

        return $this->recordAttempt(
            url: $url,
            method: $method,
            latencyMs: $this->latencyMs($startedAt),
            outcome: $this->classifyHttpStatus($status),
            httpStatus: $status,
            responsePreview: $this->responsePreview($response->body()),
        );
    }

    private function recordAttempt(
        string $url,
        string $method,
        int $latencyMs,
        string $outcome,
        ?int $httpStatus = null,
        ?string $errorType = null,
        ?string $errorMessage = null,
        ?string $responsePreview = null,
    ): ScraperProxyAttempt {
        $host = parse_url($url, PHP_URL_HOST);

        return ScraperProxyAttempt::query()->create([
            'driver' => $this->proxyConfig->driver(),
            'endpoint_host' => $this->proxyConfig->host(),
            'endpoint_port' => $this->proxyConfig->port(),
            'target_url' => $url,
            'target_host' => is_string($host) ? $host : '',
            'method' => strtoupper($method),
            'http_status' => $httpStatus,
            'outcome' => $outcome,
            'latency_ms' => $latencyMs,
            'error_type' => $errorType,
            'error_message' => $errorMessage,
            'response_preview' => $responsePreview,
            'attempted_at' => now(),
        ]);
    }

    private function classifyHttpStatus(int $status): string
    {
        return match (true) {
            $status >= 200 && $status < 400 => 'success',
            in_array($status, [403, 429], true) => 'blocked',
            $status >= 500 => 'server_error',
            default => 'client_error',
        };
    }

    private function classifyConnectionException(ConnectionException $exception): string
    {
        $message = strtolower($exception->getMessage());

        if (str_contains($message, 'timed out') || str_contains($message, 'timeout')) {
            return 'timeout';
        }

        return 'transport_error';
    }

    private function latencyMs(float $startedAt): int
    {
        return (int) round((microtime(true) - $startedAt) * 1000);
    }

    private function responsePreview(string $body): ?string
    {
        if (! config('scraping.probe.capture_body_preview', false)) {
            return null;
        }

        return substr(trim($body), 0, 255) ?: null;
    }
}
