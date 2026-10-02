<?php

namespace Tests\Feature\Jobs;

use App\Contracts\EscortPortalClient;
use App\Exceptions\AdTemporarilyDisabledException;
use App\Jobs\ExtractPhoneFromAdJob;
use PHPUnit\Framework\Attributes\DataProvider;
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

    #[DataProvider('supportedUrls')]
    public function test_supported_portals_do_not_fall_back_to_unrelated_visible_phone_numbers(string $url): void
    {
        $client = $this->mock(EscortPortalClient::class);
        $client->shouldReceive('fetchAdHtml')->once()->with($url)
            ->andReturn('<html><footer>Contact +421900123456</footer></html>');

        $this->assertNull(app()->call([new ExtractPhoneFromAdJob($url), 'handle']));
    }

    public static function supportedUrls(): array
    {
        return [
            ['https://amaterky.sk/32116'],
            ['https://AMATERKY.SK/32116'],
            ['https://www.eurogirlsescort.com/escort/example/123/'],
        ];
    }

    public function test_uppercase_hosts_do_not_bypass_the_disabled_ad_gate(): void
    {
        $url = 'https://AMATERKY.SK/32297';
        $client = $this->mock(EscortPortalClient::class);
        $client->shouldReceive('fetchAdHtml')->once()->with($url)
            ->andReturn('<h2 class="alert-heading">Vypnuty zadavatelom</h2><footer>+421900123456</footer>');

        $this->expectException(AdTemporarilyDisabledException::class);

        app()->call([new ExtractPhoneFromAdJob($url), 'handle']);
    }
}
