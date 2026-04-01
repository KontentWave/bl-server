<?php

namespace Tests\Feature\Jobs;

use App\Jobs\ExtractPhoneFromAdJob;
use Tests\TestCase;

class ExtractPhoneFromAdJobTest extends TestCase
{
    public function test_it_extracts_the_normalized_phone_number_from_an_active_ad_fixture(): void
    {
        config()->set('services.escort_portal.driver', 'fixture');
        config()->set('services.escort_portal.fixture_directory', 'tests/Fixtures/escort_ads/active');

        $result = app()->call([new ExtractPhoneFromAdJob('https://portal.example.test/escort/miriam'), 'handle']);

        $this->assertSame('+421900123456', $result);
    }

    public function test_it_returns_null_for_a_suspended_or_missing_ad_fixture(): void
    {
        config()->set('services.escort_portal.driver', 'fixture');
        config()->set('services.escort_portal.fixture_directory', 'tests/Fixtures/escort_ads/suspended');

        $result = app()->call([new ExtractPhoneFromAdJob('https://portal.example.test/escort/miriam'), 'handle']);

        $this->assertNull($result);
    }
}
