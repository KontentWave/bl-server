<?php

namespace Tests\Feature\Jobs;

use App\Jobs\VerifyEscortAdJob;
use Tests\TestCase;

class VerifyEscortAdJobTest extends TestCase
{
    public function test_it_returns_true_for_an_active_ad_fixture(): void
    {
        config()->set('services.escort_portal.driver', 'fixture');
        config()->set('services.escort_portal.fixture_directory', 'tests/Fixtures/escort_ads/active');

        $result = app()->call([new VerifyEscortAdJob('+421900123456'), 'handle']);

        $this->assertTrue($result);
    }

    public function test_it_returns_false_for_a_suspended_or_missing_ad_fixture(): void
    {
        config()->set('services.escort_portal.driver', 'fixture');
        config()->set('services.escort_portal.fixture_directory', 'tests/Fixtures/escort_ads/suspended');

        $result = app()->call([new VerifyEscortAdJob('+421900123456'), 'handle']);

        $this->assertFalse($result);
    }
}
