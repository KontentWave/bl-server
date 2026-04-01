<?php

namespace App\Services;

use App\Contracts\EscortPortalClient;
use Illuminate\Support\Facades\File;

class FixtureEscortPortalClient implements EscortPortalClient
{
    public function fetchAdHtml(string $adUrl): ?string
    {
        $fixtureDirectory = config('services.escort_portal.fixture_directory');

        if (! is_string($fixtureDirectory) || $fixtureDirectory === '') {
            return null;
        }

        $fixturePath = base_path(trim($fixtureDirectory, '/').'/'.$this->fixtureNameFromUrl($adUrl).'.html');

        if (! File::exists($fixturePath)) {
            return null;
        }

        return File::get($fixturePath);
    }

    private function fixtureNameFromUrl(string $adUrl): string
    {
        $path = (string) parse_url($adUrl, PHP_URL_PATH);
        $trimmedPath = trim($path, '/');

        if ($trimmedPath === '') {
            return 'root';
        }

        return preg_replace('/[^a-z0-9]+/i', '_', $trimmedPath) ?? 'unknown';
    }
}
