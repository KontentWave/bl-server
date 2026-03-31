<?php

namespace App\Services;

use App\Contracts\EscortPortalClient;
use Illuminate\Support\Facades\File;

class FixtureEscortPortalClient implements EscortPortalClient
{
    public function __construct(
        private readonly EscortPhoneNumberNormalizer $escortPhoneNumberNormalizer,
    ) {
    }

    public function fetchAdHtml(string $phoneNumber): ?string
    {
        $fixtureDirectory = config('services.escort_portal.fixture_directory');

        if (! is_string($fixtureDirectory) || $fixtureDirectory === '') {
            return null;
        }

        $fixturePath = base_path(
            trim($fixtureDirectory, '/').'/'.$this->escortPhoneNumberNormalizer->normalize($phoneNumber).'.html',
        );

        if (! File::exists($fixturePath)) {
            return null;
        }

        return File::get($fixturePath);
    }
}
