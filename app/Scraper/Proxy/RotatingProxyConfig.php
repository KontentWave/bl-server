<?php

namespace App\Scraper\Proxy;

class RotatingProxyConfig
{
    public function driver(): string
    {
        return (string) config('scraping.proxy.driver', 'none');
    }

    public function isEnabled(): bool
    {
        return $this->driver() !== 'none'
            && $this->host() !== ''
            && $this->username() !== ''
            && $this->password() !== '';
    }

    public function scheme(): string
    {
        return (string) config('scraping.proxy.scheme', 'http');
    }

    public function host(): string
    {
        return trim((string) config('scraping.proxy.host', ''));
    }

    public function port(): int
    {
        return (int) config('scraping.proxy.port', 80);
    }

    public function username(): string
    {
        return trim((string) config('scraping.proxy.username', ''));
    }

    public function password(): string
    {
        return trim((string) config('scraping.proxy.password', ''));
    }

    public function connectTimeout(): int
    {
        return (int) config('scraping.proxy.connect_timeout', 10);
    }

    public function timeout(): int
    {
        return (int) config('scraping.proxy.timeout', 10);
    }

    public function proxyUrl(): ?string
    {
        if (! $this->isEnabled()) {
            return null;
        }

        return sprintf(
            '%s://%s:%s@%s:%d',
            $this->scheme(),
            rawurlencode($this->username()),
            rawurlencode($this->password()),
            $this->host(),
            $this->port(),
        );
    }
}
