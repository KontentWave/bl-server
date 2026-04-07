<?php

namespace Tests\Feature\Reports;

use App\Models\Client;
use App\Models\ClientFeatureLevel;
use App\Models\DeviceBinding;
use App\Models\Report;
use App\Services\DeviceSignatureService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StoreReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_hashes_raw_phone_input_and_keeps_phase_two_tables_zero_knowledge(): void
    {
        [$privateKey, $publicKey] = $this->generateKeyPair();
        $this->bindDevice('+421900111222', $publicKey);

        $response = $this->postJson('/api/reports', [
            'client_phone_number' => '+421900333444',
            'feature' => 'no_show',
            'public_key' => $publicKey,
            'signature' => $this->signReportPayload($privateKey, '+421900333444', 'no_show', $publicKey),
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('code', 'report.created')
            ->assertJsonPath('data.level', 'level_1')
            ->assertJsonPath('data.unique_reporter_count', 1);

        $client = Client::query()->firstOrFail();
        $report = Report::query()->firstOrFail();
        $featureLevel = ClientFeatureLevel::query()->firstOrFail();

        $this->assertSame(hash('sha256', '+421900333444'), $client->client_hash);
        $this->assertSame(hash('sha256', '+421900111222'), $report->reporter_hash);
        $this->assertSame('no_show', $report->feature);
        $this->assertSame(64, strlen($client->client_hash));
        $this->assertSame(64, strlen($report->reporter_hash));
        $this->assertSame(1, $featureLevel->unique_reporter_count);
        $this->assertFalse($featureLevel->is_level_two);
        $this->assertDatabaseMissing('clients', ['client_hash' => '+421900333444']);
        $this->assertDatabaseMissing('reports', ['reporter_hash' => '+421900111222']);
    }

    public function test_it_keeps_a_client_in_level_one_with_one_or_two_unique_reports(): void
    {
        [$firstPrivateKey, $firstPublicKey] = $this->generateKeyPair();
        [$secondPrivateKey, $secondPublicKey] = $this->generateKeyPair();

        $this->bindDevice('+421900111222', $firstPublicKey);
        $this->bindDevice('+421900111333', $secondPublicKey);

        $this->submitReport('+421900333444', 'non_payment', $firstPrivateKey, $firstPublicKey);
        $this->submitReport('+421900333444', 'non_payment', $secondPrivateKey, $secondPublicKey);

        $featureLevel = ClientFeatureLevel::query()->where('feature', 'non_payment')->firstOrFail();

        $this->assertSame(2, $featureLevel->unique_reporter_count);
        $this->assertFalse($featureLevel->is_level_two);
        $this->assertNull($featureLevel->promoted_at);
    }

    public function test_it_rejects_duplicate_reports_from_the_same_bound_reporter(): void
    {
        [$privateKey, $publicKey] = $this->generateKeyPair();
        $this->bindDevice('+421900111222', $publicKey);

        $this->submitReport('+421900333444', 'aggressive', $privateKey, $publicKey);

        $duplicateResponse = $this->postJson('/api/reports', [
            'client_phone_number' => '+421900333444',
            'feature' => 'aggressive',
            'public_key' => $publicKey,
            'signature' => $this->signReportPayload($privateKey, '+421900333444', 'aggressive', $publicKey),
        ]);

        $duplicateResponse
            ->assertUnprocessable()
            ->assertJsonPath('code', 'duplicate_report')
            ->assertJsonValidationErrors('feature');

        $this->assertSame(1, Report::query()->count());
        $this->assertSame(1, ClientFeatureLevel::query()->firstOrFail()->unique_reporter_count);
    }

    public function test_it_promotes_a_feature_to_level_two_after_three_distinct_reporters(): void
    {
        [$privateKeyA, $publicKeyA] = $this->generateKeyPair();
        [$privateKeyB, $publicKeyB] = $this->generateKeyPair();
        [$privateKeyC, $publicKeyC] = $this->generateKeyPair();

        $this->bindDevice('+421900111221', $publicKeyA);
        $this->bindDevice('+421900111222', $publicKeyB);
        $this->bindDevice('+421900111223', $publicKeyC);

        $this->submitReport('+421900333444', 'non_payment', $privateKeyA, $publicKeyA);
        $this->submitReport('+421900333444', 'non_payment', $privateKeyB, $publicKeyB);
        $response = $this->submitReport('+421900333444', 'non_payment', $privateKeyC, $publicKeyC);

        $response
            ->assertCreated()
            ->assertJsonPath('data.unique_reporter_count', 3)
            ->assertJsonPath('data.level', 'level_2')
            ->assertJsonPath('data.ready_for_sync', true);

        $featureLevel = ClientFeatureLevel::query()->where('feature', 'non_payment')->firstOrFail();

        $this->assertTrue($featureLevel->is_level_two);
        $this->assertNotNull($featureLevel->promoted_at);
    }

    public function test_it_counts_features_independently_for_the_same_client(): void
    {
        [$privateKeyA, $publicKeyA] = $this->generateKeyPair();
        [$privateKeyB, $publicKeyB] = $this->generateKeyPair();
        [$privateKeyC, $publicKeyC] = $this->generateKeyPair();

        $this->bindDevice('+421900111221', $publicKeyA);
        $this->bindDevice('+421900111222', $publicKeyB);
        $this->bindDevice('+421900111223', $publicKeyC);

        $this->submitReport('+421900333444', 'no_show', $privateKeyA, $publicKeyA);
        $this->submitReport('+421900333444', 'no_show', $privateKeyB, $publicKeyB);
        $response = $this->submitReport('+421900333444', 'refused_protection', $privateKeyC, $publicKeyC);

        $response
            ->assertCreated()
            ->assertJsonPath('data.feature', 'refused_protection')
            ->assertJsonPath('data.level', 'level_1');

        $noShowLevel = ClientFeatureLevel::query()->where('feature', 'no_show')->firstOrFail();
        $refusedProtectionLevel = ClientFeatureLevel::query()->where('feature', 'refused_protection')->firstOrFail();

        $this->assertSame(2, $noShowLevel->unique_reporter_count);
        $this->assertFalse($noShowLevel->is_level_two);
        $this->assertSame(1, $refusedProtectionLevel->unique_reporter_count);
        $this->assertFalse($refusedProtectionLevel->is_level_two);
    }

    private function bindDevice(string $phoneNumber, string $publicKey): DeviceBinding
    {
        return DeviceBinding::query()->create([
            'phone_number' => $phoneNumber,
            'public_key' => app(DeviceSignatureService::class)->normalizePublicKey($publicKey),
            'verified_at' => now(),
        ]);
    }

    private function submitReport(string $clientPhoneNumber, string $feature, string $privateKey, string $publicKey)
    {
        return $this->postJson('/api/reports', [
            'client_phone_number' => $clientPhoneNumber,
            'feature' => $feature,
            'public_key' => $publicKey,
            'signature' => $this->signReportPayload($privateKey, $clientPhoneNumber, $feature, $publicKey),
        ]);
    }

    private function signReportPayload(string $privateKey, string $clientPhoneNumber, string $feature, string $publicKey): string
    {
        $payload = app(DeviceSignatureService::class)->reportPayload($clientPhoneNumber, $feature, $publicKey);

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
