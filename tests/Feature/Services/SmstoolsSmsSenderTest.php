<?php

namespace Tests\Feature\Services;

use App\Exceptions\SmsDispatchFailedException;
use App\Services\SmstoolsSmsSender;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SmstoolsSmsSenderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        config()->set('services.sms.smstools.api_key', 'test-api-key');
        config()->set('services.sms.from', 'Blacklist');
        config()->set('services.sms.smstools.endpoint', 'https://api.smstools.sk/3/send_batch');
    }

    public function test_it_throws_when_credentials_are_missing(): void
    {
        config()->set('services.sms.smstools.api_key', null);

        $this->expectException(SmsDispatchFailedException::class);

        app(SmstoolsSmsSender::class)->sendOtp('+421903223183', '123456');
    }

    public function test_it_dispatches_an_sms_via_smstools(): void
    {
        config()->set('services.sms.smstools.api_key', 'api-key');
        config()->set('services.sms.smstools.endpoint', 'https://api.smstools.sk/3/send_batch');
        config()->set('services.sms.from', 'Blacklist');

        Http::fake(function ($request) {
            TestCase::assertSame('https://api.smstools.sk/3/send_batch', (string) $request->url());

            $payload = $request->data();

            TestCase::assertSame('api-key', $payload['auth']['apikey']);
            TestCase::assertSame('Your Blacklist verification code is 123456', $payload['data']['message']);
            TestCase::assertSame('Blacklist', $payload['data']['sender']['text']);
            TestCase::assertSame('+421903223183', $payload['data']['recipients'][0]['phonenr']);

            return Http::response([
                'id' => 'OK',
                'note' => null,
                'data' => [
                    'batch_id' => 12345,
                    'recipients' => [
                        'accepted' => [[
                            'msg_id' => 22345,
                            'phonenr' => '+421903223183',
                        ]],
                    ],
                ],
            ], 200);
        });

        Log::spy();

        app(SmstoolsSmsSender::class)->sendOtp('+421903223183', '123456');

        Log::shouldHaveReceived('info')->with('sms.otp_dispatched', [
            'provider' => 'smstools',
            'phone_number' => '+421***183',
            'batch_id' => 12345,
            'message_id' => 22345,
        ])->once();
    }

    public function test_it_wraps_provider_failures(): void
    {
        config()->set('services.sms.smstools.api_key', 'api-key');

        Http::fake(fn () => Http::response([
            'id' => 'NESPRAVNE_MENO_HESLO',
            'note' => 'Chybné meno alebo heslo',
        ], 200));

        $this->expectException(SmsDispatchFailedException::class);

        app(SmstoolsSmsSender::class)->sendOtp('+421917047260', '123456');
    }

    public function test_it_wraps_transport_failure_without_retrying_or_logging_provider_details(): void
    {
        $attempts = 0;
        Log::spy();
        config()->set('services.sms.smstools.connect_timeout', 2);
        config()->set('services.sms.smstools.timeout', 7);

        Http::fake(function ($request, $options) use (&$attempts) {
            $attempts++;
            $this->assertSame(2, $options['connect_timeout']);
            $this->assertSame(7, $options['timeout']);

            throw new ConnectionException('Sensitive provider transport details');
        });

        try {
            app(SmstoolsSmsSender::class)->sendOtp('+421900123456', '000000');
            $this->fail('Expected an SMS domain failure.');
        } catch (SmsDispatchFailedException $exception) {
            $this->assertSame(503, $exception->status());
            $this->assertSame(['retryable' => true], $exception->meta());
            $this->assertSame(['provider' => 'smstools', 'reason' => 'transport_error'], $exception->logContext());
        }

        $this->assertSame(1, $attempts);
        Log::shouldNotHaveReceived('info');
    }

    #[DataProvider('invalidResponses')]
    public function test_it_rejects_malformed_partial_or_rejected_responses(mixed $payload, int $status): void
    {
        Http::fake(fn () => Http::response($payload, $status));
        Log::spy();

        try {
            app(SmstoolsSmsSender::class)->sendOtp('+421900123456', '000000');
            $this->fail('Expected an SMS domain failure.');
        } catch (SmsDispatchFailedException $exception) {
            $this->assertSame('sms_dispatch_failed', $exception->apiCode());
            $this->assertSame(503, $exception->status());
            $this->assertSame(['retryable' => true], $exception->meta());
            $this->assertArrayNotHasKey('provider_note', $exception->logContext());
            $this->assertArrayNotHasKey('provider_code', $exception->logContext());
        }

        Http::assertSentCount(1);
        Log::shouldNotHaveReceived('info');
    }

    public static function invalidResponses(): array
    {
        $accepted = ['phonenr' => '+421900123456', 'msg_id' => 22345];

        return [
            'non JSON' => ['not-json', 200],
            'scalar JSON' => ['"OK"', 200],
            'empty response' => [[], 200],
            'provider rejection' => [['id' => 'NEDOSTATOK_KREDITU', 'note' => 'Sensitive provider details'], 200],
            'HTTP failure' => [['id' => 'OK'], 503],
            'missing acceptance' => [['id' => 'OK'], 200],
            'malformed data' => [['id' => 'OK', 'data' => 'invalid'], 200],
            'malformed recipients' => [['id' => 'OK', 'data' => ['recipients' => 'invalid']], 200],
            'empty acceptance' => [['id' => 'OK', 'data' => ['batch_id' => 12345, 'recipients' => ['accepted' => []]]], 200],
            'wrong recipient' => [['id' => 'OK', 'data' => ['batch_id' => 12345, 'recipients' => ['accepted' => [['phonenr' => '+421900000001', 'msg_id' => 22345]]]]], 200],
            'missing message id' => [['id' => 'OK', 'data' => ['batch_id' => 12345, 'recipients' => ['accepted' => [['phonenr' => '+421900123456']]]]], 200],
            'missing batch id' => [['id' => 'OK', 'data' => ['recipients' => ['accepted' => [$accepted]]]], 200],
            'malformed acceptance row' => [['id' => 'OK', 'data' => ['batch_id' => 12345, 'recipients' => ['accepted' => ['invalid']]]], 200],
        ];
    }

    public function test_it_accepts_international_digits_and_numeric_string_identifiers(): void
    {
        Http::fake(fn () => Http::response([
            'id' => 'OK',
            'data' => [
                'batch_id' => '12345',
                'recipients' => ['accepted' => [
                    ['phonenr' => '+421900000001', 'msg_id' => 11111],
                    ['phonenr' => '421900123456', 'msg_id' => '22345'],
                ]],
            ],
        ], 200));

        app(SmstoolsSmsSender::class)->sendOtp('+421900123456', '000000');

        Http::assertSentCount(1);
    }
}
