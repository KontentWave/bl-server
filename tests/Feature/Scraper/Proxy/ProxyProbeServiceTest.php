<?php

namespace Tests\Feature\Scraper\Proxy;

use App\Models\ScraperProxyAttempt;
use App\Scraper\Proxy\ProxyProbeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ProxyProbeServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('scraping.proxy.driver', 'webshare_rotating');
        config()->set('scraping.proxy.scheme', 'http');
        config()->set('scraping.proxy.host', 'p.webshare.io');
        config()->set('scraping.proxy.port', 80);
        config()->set('scraping.proxy.username', 'rotate-user');
        config()->set('scraping.proxy.password', 'rotate-pass');
        config()->set('scraping.proxy.connect_timeout', 5);
        config()->set('scraping.proxy.timeout', 5);
    }

    public function test_it_records_a_successful_probe_attempt(): void
    {
        Http::fake([
            'https://amaterky.sk/' => Http::response('<html>ok</html>', 200),
        ]);

        $attempt = app(ProxyProbeService::class)->probe('https://amaterky.sk/');

        $this->assertSame('success', $attempt->outcome);
        $this->assertSame(200, $attempt->http_status);
        $this->assertSame('amaterky.sk', $attempt->target_host);

        $this->assertDatabaseHas('scraper_proxy_attempts', [
            'id' => $attempt->id,
            'driver' => 'webshare_rotating',
            'endpoint_host' => 'p.webshare.io',
            'target_host' => 'amaterky.sk',
            'http_status' => 200,
            'outcome' => 'success',
        ]);
    }

    public function test_it_records_blocked_http_statuses(): void
    {
        Http::fake([
            'https://amaterky.sk/' => Http::response('blocked', 403),
        ]);

        $attempt = app(ProxyProbeService::class)->probe('https://amaterky.sk/');

        $this->assertSame('blocked', $attempt->outcome);
        $this->assertSame(403, $attempt->http_status);
    }

    public function test_it_records_timeouts(): void
    {
        Http::fake(function () {
            throw new ConnectionException('Connection timed out after 5000 milliseconds.');
        });

        $timeoutAttempt = app(ProxyProbeService::class)->probe('https://amaterky.sk/');

        $this->assertSame('timeout', $timeoutAttempt->outcome);
        $this->assertNull($timeoutAttempt->http_status);

        $this->assertSame(1, ScraperProxyAttempt::query()->count());
    }

    public function test_it_records_transport_errors(): void
    {
        Http::fake(function () {
            throw new ConnectionException('Could not connect to proxy host.');
        });

        $transportAttempt = app(ProxyProbeService::class)->probe('https://amaterky.sk/');

        $this->assertSame('transport_error', $transportAttempt->outcome);
        $this->assertNull($transportAttempt->http_status);

        $this->assertSame(1, ScraperProxyAttempt::query()->count());
    }
}
