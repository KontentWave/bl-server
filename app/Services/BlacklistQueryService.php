<?php

namespace App\Services;

use App\Exceptions\BlacklistQueryUnauthorizedException;
use App\Models\Client;
use App\Models\ClientFeatureLevel;
use App\Models\DeviceBinding;

class BlacklistQueryService
{
    public function __construct(
        private readonly DeviceSignatureService $deviceSignatureService,
    ) {
    }

    public function check(string $targetHash, ?string $publicKey, ?string $signature): array
    {
        if (! $publicKey || ! $signature) {
            throw BlacklistQueryUnauthorizedException::create();
        }

        $normalizedPublicKey = $this->deviceSignatureService->normalizePublicKey($publicKey);
        $deviceBinding = DeviceBinding::query()
            ->where('public_key', $normalizedPublicKey)
            ->first();

        if (! $deviceBinding) {
            throw BlacklistQueryUnauthorizedException::create();
        }

        if (! $this->deviceSignatureService->verifyBlacklistCheck($targetHash, $normalizedPublicKey, $signature)) {
            throw BlacklistQueryUnauthorizedException::create();
        }

        $client = Client::query()
            ->where('client_hash', $targetHash)
            ->first();

        if (! $client) {
            return [
                'target_hash' => $targetHash,
                'features' => [],
            ];
        }

        $features = ClientFeatureLevel::query()
            ->where('client_id', $client->id)
            ->where('is_level_two', true)
            ->orderBy('feature')
            ->pluck('feature')
            ->map(fn (string $feature): string => config('reporting.features.'.$feature, $feature))
            ->values()
            ->all();

        return [
            'target_hash' => $targetHash,
            'features' => $features,
        ];
    }
}
