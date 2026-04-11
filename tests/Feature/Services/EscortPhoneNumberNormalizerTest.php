<?php

namespace Tests\Feature\Services;

use App\Services\EscortPhoneNumberNormalizer;
use Tests\TestCase;

class EscortPhoneNumberNormalizerTest extends TestCase
{
    public function test_it_normalizes_a_local_slovak_mobile_number_to_e164(): void
    {
        $result = app(EscortPhoneNumberNormalizer::class)->normalizeE164('0944 493 008');

        $this->assertSame('+421944493008', $result);
    }

    public function test_it_normalizes_a_dirty_international_prefix_to_e164(): void
    {
        $result = app(EscortPhoneNumberNormalizer::class)->normalizeE164('Tel: 00421 903-123-456 (WhatsApp only)');

        $this->assertSame('+421903123456', $result);
    }
}
