<?php

namespace Tests\Feature\Blacklist;

use App\Models\Client;
use App\Models\ClientFeatureLevel;
use App\Models\DeviceBinding;
use App\Services\DeviceSignatureService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckBlacklistTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_level_two_features_for_a_known_target_hash(): void
    {
        [$privateKey, $publicKey] = $this->generateKeyPair();
        $this->bindDevice('+421900111222', $publicKey);

        $client = Client::query()->create([
            'client_hash' => hash('sha256', '+421900333444'),
        ]);

        ClientFeatureLevel::query()->create([
            'client_id' => $client->id,
            'feature' => 'aggressive',
            'unique_reporter_count' => 3,
            'is_level_two' => true,
            'promoted_at' => now(),
        ]);

        ClientFeatureLevel::query()->create([
            'client_id' => $client->id,
            'feature' => 'no_show',
            'unique_reporter_count' => 3,
            'is_level_two' => true,
            'promoted_at' => now(),
        ]);

        $targetHash = hash('sha256', '+421900333444');

        $response = $this->postJson('/api/blacklist/check', [
            'target_hash' => $targetHash,
            'public_key' => $publicKey,
            'signature' => $this->signBlacklistPayload($privateKey, $targetHash, $publicKey),
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('code', 'blacklist.checked')
            ->assertJsonPath('data.target_hash', $targetHash)
            ->assertJsonPath('data.features', ['Aggressive', 'No-Show']);
    }

    public function test_it_returns_an_empty_feature_array_for_level_one_or_unknown_targets(): void
    {
        [$privateKey, $publicKey] = $this->generateKeyPair();
        $this->bindDevice('+421900111222', $publicKey);

        $levelOneClient = Client::query()->create([
            'client_hash' => hash('sha256', '+421900333445'),
        ]);

        ClientFeatureLevel::query()->create([
            'client_id' => $levelOneClient->id,
            'feature' => 'non_payment',
            'unique_reporter_count' => 2,
            'is_level_two' => false,
        ]);

        $levelOneHash = hash('sha256', '+421900333445');
        $unknownHash = hash('sha256', '+421900333446');

        $levelOneResponse = $this->postJson('/api/blacklist/check', [
            'target_hash' => $levelOneHash,
            'public_key' => $publicKey,
            'signature' => $this->signBlacklistPayload($privateKey, $levelOneHash, $publicKey),
        ]);

        $unknownResponse = $this->postJson('/api/blacklist/check', [
            'target_hash' => $unknownHash,
            'public_key' => $publicKey,
            'signature' => $this->signBlacklistPayload($privateKey, $unknownHash, $publicKey),
        ]);

        $levelOneResponse
            ->assertOk()
            ->assertJsonPath('data.features', []);

        $unknownResponse
            ->assertOk()
            ->assertJsonPath('data.features', []);
    }

    public function test_it_rejects_queries_with_invalid_or_missing_authorization(): void
    {
        [$privateKey, $publicKey] = $this->generateKeyPair();
        $this->bindDevice('+421900111222', $publicKey);

        $targetHash = hash('sha256', '+421900333444');

        $missingAuthorizationResponse = $this->postJson('/api/blacklist/check', [
            'target_hash' => $targetHash,
        ]);

        $invalidSignatureResponse = $this->postJson('/api/blacklist/check', [
            'target_hash' => $targetHash,
            'public_key' => $publicKey,
            'signature' => base64_encode('invalid-signature'),
        ]);

        $missingAuthorizationResponse
            ->assertUnauthorized()
            ->assertJsonPath('code', 'blacklist_query_unauthorized');

        $invalidSignatureResponse
            ->assertUnauthorized()
            ->assertJsonPath('code', 'blacklist_query_unauthorized');
    }

    private function bindDevice(string $phoneNumber, string $publicKey): DeviceBinding
    {
        return DeviceBinding::query()->create([
            'phone_number' => $phoneNumber,
            'public_key' => app(DeviceSignatureService::class)->normalizePublicKey($publicKey),
            'verified_at' => now(),
        ]);
    }

    private function signBlacklistPayload(string $privateKey, string $targetHash, string $publicKey): string
    {
        $payload = app(DeviceSignatureService::class)->blacklistCheckPayload($targetHash, $publicKey);

        openssl_sign($payload, $signature, $privateKey, OPENSSL_ALGO_SHA256);

        return base64_encode($signature);
    }

    private function generateKeyPair(): array
    {
        $privateKey = openssl_pkey_new([
            'private_key_type' => OPENSSL_KEYTYPE_EC,
            'curve_name' => 'prime256v1',
        ]);

        openssl_pkey_export($privateKey, $privateKeyPem);

        $details = openssl_pkey_get_details($privateKey);

        return [$privateKeyPem, $details['key']];
    }
}
