<?php

namespace Tests\Feature\Services;

use App\Exceptions\SmsDispatchFailedException;
use App\Services\VonageSmsSender;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;
use Vonage\Client;
use Vonage\Client\Credentials\Basic;
use Vonage\SMS\Collection;
use Vonage\SMS\Message\SMS;

class VonageSmsSenderTest extends TestCase
{
    public function test_it_throws_when_credentials_are_missing(): void
    {
        config()->set('services.sms.vonage.key', null);
        config()->set('services.sms.vonage.secret', null);

        $this->expectException(SmsDispatchFailedException::class);

        app(VonageSmsSender::class)->sendOtp('+421903223183', '123456');
    }

    public function test_it_dispatches_an_sms_via_vonage(): void
    {
        config()->set('services.sms.vonage.key', 'key');
        config()->set('services.sms.vonage.secret', 'secret');
        config()->set('services.sms.from', 'Blacklist');

        Log::spy();

        $sender = new class extends VonageSmsSender
        {
            protected function makeClient(string $key, string $secret): Client
            {
                return new class(new Basic($key, $secret)) extends Client
                {
                    public function sms(): object
                    {
                        return new class
                        {
                            public function send(SMS $message): Collection
                            {
                                TestCase::assertSame('+421903223183', $message->getTo());
                                TestCase::assertSame('Blacklist', $message->getFrom());
                                TestCase::assertSame('Your Blacklist verification code is 123456', $message->getMessage());

                                return new Collection([
                                    'message-count' => 1,
                                    'messages' => [[
                                        'to' => '+421903223183',
                                        'message-id' => 'abc123',
                                        'status' => '0',
                                        'remaining-balance' => '1.50',
                                        'message-price' => '0.05',
                                        'network' => '23102',
                                    ]],
                                ]);
                            }
                        };
                    }
                };
            }
        };

        $sender->sendOtp('+421903223183', '123456');

        Log::shouldHaveReceived('info')->with('sms.otp_dispatched', [
            'provider' => 'vonage',
            'phone_number' => '+421***183',
            'message_id' => 'abc123',
            'remaining_balance' => '1.50',
        ])->once();
    }

    public function test_it_wraps_provider_failures(): void
    {
        config()->set('services.sms.vonage.key', 'key');
        config()->set('services.sms.vonage.secret', 'secret');

        $sender = new class extends VonageSmsSender
        {
            protected function makeClient(string $key, string $secret): Client
            {
                return new class(new Basic($key, $secret)) extends Client
                {
                    public function sms(): object
                    {
                        return new class
                        {
                            public function send(SMS $message): never
                            {
                                throw new \RuntimeException('Non White-Listed Destination', 29);
                            }
                        };
                    }
                };
            }
        };

        $this->expectException(SmsDispatchFailedException::class);

        $sender->sendOtp('+421917047260', '123456');
    }
}
