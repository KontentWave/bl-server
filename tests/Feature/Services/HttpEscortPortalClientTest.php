<?php

namespace Tests\Feature\Services;

use App\Contracts\EscortPortalClient;
use App\Exceptions\EscortPortalTimeoutException;
use App\Exceptions\EscortPortalUnavailableException;
use App\Exceptions\InvalidAdUrlException;
use App\Services\EscortAdUrlPolicy;
use App\Services\HttpEscortPortalClient;
use App\Services\RenewalAdInspector;
use GuzzleHttp\Psr7\Response as Psr7Response;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class HttpEscortPortalClientTest extends TestCase
{
    private MockInterface $adUrlPolicy;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        config()->set('scraping.proxy.driver', 'none');
        config()->set('services.escort_portal.proxy', null);
        $this->adUrlPolicy = Mockery::mock(EscortAdUrlPolicy::class);
        $this->adUrlPolicy->makePartial()->shouldAllowMockingProtectedMethods();
        $this->adUrlPolicy->shouldReceive('lookupAddresses')->andReturn(['8.8.8.8'])->byDefault();
        $this->app->instance(EscortAdUrlPolicy::class, $this->adUrlPolicy);
    }

    public function test_it_fetches_ad_html_via_the_http_client(): void
    {
        config()->set('services.escort_portal.user_agent', 'BlacklistBackend/Test');
        config()->set('services.escort_portal.timeout', 5);

        Http::fake([
            'https://www.amaterky.sk/32297' => Http::response('<html>+421 900 123 456</html>', 200),
        ]);

        $html = app(HttpEscortPortalClient::class)->fetchAdHtml('https://www.amaterky.sk/32297');

        $this->assertSame('<html>+421 900 123 456</html>', $html);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://www.amaterky.sk/32297'
                && $request->header('User-Agent')[0] === 'BlacklistBackend/Test';
        });
    }

    #[DataProvider('unknownRenewalResponses')]
    public function test_renewal_http_failures_are_unknown_and_never_retry_in_request(int $status): void
    {
        Http::fake(['https://www.amaterky.sk/fixture' => Http::response(
            '<h2 class="alert-heading">Vypnutý zadávateľom</h2>', $status, ['Retry-After' => '7200'],
        )]);
        $this->app->instance(EscortPortalClient::class, app(HttpEscortPortalClient::class));
        $result = app(RenewalAdInspector::class)->inspect('https://www.amaterky.sk/fixture', '+421900123456');
        $this->assertSame('unknown', $result['status']);
        $this->assertSame(7200, $result['retry_after']);
        Http::assertSentCount(1);
    }

    public static function unknownRenewalResponses(): array
    {
        return [[404], [410], [302], [429], [500], [502], [503]];
    }

    public function test_renewal_transport_retry_after_is_validated_and_cleared_between_fetches(): void
    {
        Http::fakeSequence()->push('', 429, ['Retry-After' => '7200'])->push('', 429, ['Retry-After' => 'garbage']);
        $client = app(HttpEscortPortalClient::class);
        $client->fetchAdHtml('https://www.amaterky.sk/fixture');
        $this->assertSame(7200, $client->renewalRetryAfter());
        $client->fetchAdHtml('https://www.amaterky.sk/fixture');
        $this->assertSame(0, $client->renewalRetryAfter());
        Http::assertSentCount(2);
    }

    public function test_it_returns_null_when_the_portal_response_is_not_successful(): void
    {
        Http::fake([
            'https://www.amaterky.sk/32297' => Http::response('not found', 404),
        ]);

        $html = app(HttpEscortPortalClient::class)->fetchAdHtml('https://www.amaterky.sk/32297');

        $this->assertNull($html);
    }

    public function test_it_raises_a_timeout_exception_when_the_http_request_times_out(): void
    {
        Http::fake(function () {
            throw new ConnectionException('Timed out.');
        });

        $this->expectException(EscortPortalTimeoutException::class);

        app(HttpEscortPortalClient::class)->fetchAdHtml('https://www.amaterky.sk/32297');
    }

    public function test_it_raises_an_unavailable_exception_when_the_portal_returns_a_server_error(): void
    {
        Http::fake([
            'https://www.amaterky.sk/32297' => Http::response('bad gateway', 502),
        ]);

        $this->expectException(EscortPortalUnavailableException::class);

        app(HttpEscortPortalClient::class)->fetchAdHtml('https://www.amaterky.sk/32297');
    }

    public function test_it_raises_an_unavailable_exception_when_the_http_client_throws_a_server_error_request_exception(): void
    {
        Http::fake(function () {
            throw (new Response(new Psr7Response(502, [], 'bad gateway')))->toException();
        });

        $this->expectException(EscortPortalUnavailableException::class);

        app(HttpEscortPortalClient::class)->fetchAdHtml('https://www.amaterky.sk/32297');
    }

    #[DataProvider('unsafeUrls')]
    public function test_it_rejects_unsafe_urls_before_any_http_request(string $url): void
    {
        Http::preventStrayRequests();
        Http::fake();

        try {
            app(HttpEscortPortalClient::class)->fetchAdHtml($url);
            $this->fail('Expected an invalid ad URL.');
        } catch (InvalidAdUrlException) {
            Http::assertNothingSent();
        }
    }

    public static function unsafeUrls(): array
    {
        return [
            ['http://amaterky.sk/32297'],
            ['https://127.0.0.1/ad'],
            ['https://[::1]/ad'],
            ['https://169.254.169.254/latest/meta-data/'],
            ['https://example.com/ad'],
            ['https://amaterky.sk.example.com/ad'],
            ['https://private.amaterky.sk/ad'],
            ['https://user:password@amaterky.sk/ad'],
            ['https://amaterky.sk:8443/ad'],
            ['file:///etc/passwd'],
            ['https://portal.example.test/escort/miriam'],
        ];
    }

    public function test_it_does_not_follow_redirects(): void
    {
        Http::preventStrayRequests();
        Http::fake(function ($request, $options) {
            $this->assertFalse($options['allow_redirects']);

            return Http::response('', 302, ['Location' => 'https://127.0.0.1/ad']);
        });

        $this->assertNull(app(HttpEscortPortalClient::class)->fetchAdHtml('https://www.amaterky.sk/32297'));
        Http::assertSentCount(1);
    }

    #[DataProvider('unsafeAddresses')]
    public function test_it_rejects_non_public_dns_answers_before_http(array $addresses): void
    {
        $this->adUrlPolicy->shouldReceive('lookupAddresses')->andReturn($addresses);
        Http::fake();

        try {
            app(HttpEscortPortalClient::class)->fetchAdHtml('https://www.amaterky.sk/32297');
            $this->fail('Expected a rejected DNS destination.');
        } catch (InvalidAdUrlException) {
            Http::assertNothingSent();
        }
    }

    public static function unsafeAddresses(): array
    {
        return array_map(static fn (string $address): array => [[$address]], [
            '127.0.0.1', '10.0.0.1', '172.16.0.1', '192.168.0.1', '169.254.169.254',
            '100.64.0.1', '192.0.0.1', '198.18.0.1', '224.0.0.1', '0.0.0.0',
            '192.0.2.1', '198.51.100.1', '203.0.113.1',
            '::1', 'fc00::1', 'fe80::1', '::ffff:127.0.0.1', '2002:7f00:1::', '2001:db8::1',
        ]) + ['mixed public and private' => [['8.8.8.8', '10.0.0.1']]];
    }

    public function test_it_fails_closed_when_dns_has_no_addresses(): void
    {
        $this->adUrlPolicy->shouldReceive('lookupAddresses')->andReturn([]);
        Http::fake();

        try {
            app(HttpEscortPortalClient::class)->fetchAdHtml('https://www.amaterky.sk/32297');
            $this->fail('Expected a DNS failure.');
        } catch (EscortPortalUnavailableException) {
            Http::assertNothingSent();
        }
    }

    #[DataProvider('publicAddresses')]
    public function test_it_pins_the_validated_address_and_preserves_tls_verification(string $address, string $connectHost): void
    {
        config()->set('services.escort_portal.proxy', 'http://proxy.example.test:8080');
        $this->adUrlPolicy->shouldReceive('lookupAddresses')->once()->andReturn([$address]);
        Http::fake(function ($request, $options) use ($connectHost) {
            $this->assertFalse($options['allow_redirects']);
            $this->assertTrue($options['verify']);
            $this->assertSame('http://proxy.example.test:8080', $options['proxy']);
            $this->assertSame(['www.amaterky.sk:443:'.$connectHost.':443'], $options['curl'][CURLOPT_CONNECT_TO]);

            return Http::response('<html>safe fixture</html>', 200);
        });

        $this->assertSame('<html>safe fixture</html>', app(HttpEscortPortalClient::class)->fetchAdHtml('https://www.amaterky.sk/32297'));
        Http::assertSentCount(1);
    }

    public static function publicAddresses(): array
    {
        return [
            ['8.8.8.8', '8.8.8.8'],
            ['2606:4700:4700::1111', '[2606:4700:4700::1111]'],
        ];
    }
}
