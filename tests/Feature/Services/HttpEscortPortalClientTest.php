<?php

namespace Tests\Feature\Services;

use App\Exceptions\EscortPortalTimeoutException;
use App\Exceptions\EscortPortalUnavailableException;
use App\Services\HttpEscortPortalClient;
use GuzzleHttp\Psr7\Response as Psr7Response;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HttpEscortPortalClientTest extends TestCase
{
    public function test_it_fetches_ad_html_via_the_http_client(): void
    {
        config()->set('services.escort_portal.user_agent', 'BlacklistBackend/Test');
        config()->set('services.escort_portal.timeout', 5);

        Http::fake([
            'https://portal.example.test/escort/miriam' => Http::response('<html>+421 900 123 456</html>', 200),
        ]);

        $html = app(HttpEscortPortalClient::class)->fetchAdHtml('https://portal.example.test/escort/miriam');

        $this->assertSame('<html>+421 900 123 456</html>', $html);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://portal.example.test/escort/miriam'
                && $request->header('User-Agent')[0] === 'BlacklistBackend/Test';
        });
    }

    public function test_it_returns_null_when_the_portal_response_is_not_successful(): void
    {
        Http::fake([
            'https://portal.example.test/escort/miriam' => Http::response('not found', 404),
        ]);

        $html = app(HttpEscortPortalClient::class)->fetchAdHtml('https://portal.example.test/escort/miriam');

        $this->assertNull($html);
    }

    public function test_it_raises_a_timeout_exception_when_the_http_request_times_out(): void
    {
        Http::fake(function () {
            throw new ConnectionException('Timed out.');
        });

        $this->expectException(EscortPortalTimeoutException::class);

        app(HttpEscortPortalClient::class)->fetchAdHtml('https://portal.example.test/escort/miriam');
    }

    public function test_it_raises_an_unavailable_exception_when_the_portal_returns_a_server_error(): void
    {
        Http::fake([
            'https://portal.example.test/escort/miriam' => Http::response('bad gateway', 502),
        ]);

        $this->expectException(EscortPortalUnavailableException::class);

        app(HttpEscortPortalClient::class)->fetchAdHtml('https://portal.example.test/escort/miriam');
    }

    public function test_it_raises_an_unavailable_exception_when_the_http_client_throws_a_server_error_request_exception(): void
    {
        Http::fake(function () {
            throw (new Response(new Psr7Response(502, [], 'bad gateway')))->toException();
        });

        $this->expectException(EscortPortalUnavailableException::class);

        app(HttpEscortPortalClient::class)->fetchAdHtml('https://portal.example.test/escort/miriam');
    }
}
