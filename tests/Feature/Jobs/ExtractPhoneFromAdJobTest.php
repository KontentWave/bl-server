<?php

namespace Tests\Feature\Jobs;

use App\Exceptions\AdTemporarilyDisabledException;
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

    public function test_it_extracts_the_normalized_phone_number_from_an_active_amaterky_ad_fixture(): void
    {
        config()->set('services.escort_portal.driver', 'fixture');
        config()->set('services.escort_portal.fixture_directory', 'tests/Fixtures/escort_ads/active');
        config()->set('services.escort_portal.development_phone_override', '');

        $result = app()->call([new ExtractPhoneFromAdJob('https://amaterky.sk/32116'), 'handle']);

        $this->assertSame('+421944493008', $result);
    }

    public function test_it_extracts_the_phone_number_from_an_amaterky_sms_only_fixture(): void
    {
        config()->set('services.escort_portal.driver', 'fixture');
        config()->set('services.escort_portal.fixture_directory', 'tests/Fixtures/escort_ads/amaterky_sms_only');
        config()->set('services.escort_portal.development_phone_override', '');

        $result = app()->call([new ExtractPhoneFromAdJob('https://amaterky.sk/32116'), 'handle']);

        $this->assertSame('+421944493008', $result);
    }

    public function test_it_extracts_the_phone_number_from_an_amaterky_heading_only_fixture(): void
    {
        config()->set('services.escort_portal.driver', 'fixture');
        config()->set('services.escort_portal.fixture_directory', 'tests/Fixtures/escort_ads/amaterky_heading_only');
        config()->set('services.escort_portal.development_phone_override', '');

        $result = app()->call([new ExtractPhoneFromAdJob('https://amaterky.sk/32116'), 'handle']);

        $this->assertSame('+421944493008', $result);
    }

    public function test_it_extracts_the_phone_number_from_a_eurogirlsescort_ad_fixture(): void
    {
        config()->set('services.escort_portal.driver', 'fixture');
        config()->set('services.escort_portal.fixture_directory', 'tests/Fixtures/escort_ads/eurogirlsescort_active');
        config()->set('services.escort_portal.development_phone_override', '');

        $result = app()->call([new ExtractPhoneFromAdJob('https://www.eurogirlsescort.com/escort/karin/651611/?list=1koqwc'), 'handle']);

        $this->assertSame('+420792412818', $result);
    }

    public function test_it_extracts_the_phone_number_from_a_eurogirlsescort_obfuscated_phone_fixture(): void
    {
        config()->set('services.escort_portal.driver', 'fixture');
        config()->set('services.escort_portal.fixture_directory', 'tests/Fixtures/escort_ads/eurogirlsescort_obfuscated_phone');
        config()->set('services.escort_portal.development_phone_override', '');

        $result = app()->call([new ExtractPhoneFromAdJob('https://www.eurogirlsescort.com/escort/amber/454/?list=1koqwc'), 'handle']);

        $this->assertSame('+421944524889', $result);
    }

    public function test_it_returns_null_for_an_amaterky_fixture_without_a_phone_number(): void
    {
        config()->set('services.escort_portal.driver', 'fixture');
        config()->set('services.escort_portal.fixture_directory', 'tests/Fixtures/escort_ads/amaterky_missing_phone');

        $result = app()->call([new ExtractPhoneFromAdJob('https://amaterky.sk/32116'), 'handle']);

        $this->assertNull($result);
    }

    public function test_it_returns_null_for_a_suspended_or_missing_ad_fixture(): void
    {
        config()->set('services.escort_portal.driver', 'fixture');
        config()->set('services.escort_portal.fixture_directory', 'tests/Fixtures/escort_ads/suspended');

        $result = app()->call([new ExtractPhoneFromAdJob('https://portal.example.test/escort/miriam'), 'handle']);

        $this->assertNull($result);
    }

    public function test_it_returns_null_for_a_suspended_amaterky_ad_fixture(): void
    {
        config()->set('services.escort_portal.driver', 'fixture');
        config()->set('services.escort_portal.fixture_directory', 'tests/Fixtures/escort_ads/suspended');

        $result = app()->call([new ExtractPhoneFromAdJob('https://amaterky.sk/32116'), 'handle']);

        $this->assertNull($result);
    }

    public function test_it_raises_a_distinct_exception_for_a_temporarily_disabled_amaterky_ad_fixture(): void
    {
        config()->set('services.escort_portal.driver', 'fixture');
        config()->set('services.escort_portal.fixture_directory', 'tests/Fixtures/escort_ads/temporarily_disabled');

        $this->expectException(AdTemporarilyDisabledException::class);

        app()->call([new ExtractPhoneFromAdJob('https://amaterky.sk/32297'), 'handle']);
    }
}
