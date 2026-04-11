<?php

namespace Tests\Feature\Console;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ProbeScraperProxyCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('scraping.proxy.driver', 'webshare_rotating');
        config()->set('scraping.proxy.host', 'p.webshare.io');
        config()->set('scraping.proxy.port', 80);
        config()->set('scraping.proxy.username', 'rotate-user');
        config()->set('scraping.proxy.password', 'rotate-pass');
    }

    public function test_the_probe_command_records_multiple_attempts(): void
    {
        Http::fake([
            'https://amaterky.sk/' => Http::sequence()
                ->push('ok', 200)
                ->push('blocked', 403),
        ]);

        $exitCode = Artisan::call('scraper:probe-proxy', [
            '--url' => 'https://amaterky.sk/',
            '--attempts' => 2,
        ]);

        $this->assertSame(0, $exitCode);
        $this->assertDatabaseCount('scraper_proxy_attempts', 2);
        $this->assertDatabaseHas('scraper_proxy_attempts', ['outcome' => 'success', 'http_status' => 200]);
        $this->assertDatabaseHas('scraper_proxy_attempts', ['outcome' => 'blocked', 'http_status' => 403]);
    }
}
