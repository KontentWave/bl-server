<?php

namespace Tests\Feature\Services;

use App\Exceptions\SmsDispatchFailedException;
use App\Services\SmstoolsSmsSender;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class SmstoolsSmsSenderTest extends TestCase
{
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
}
