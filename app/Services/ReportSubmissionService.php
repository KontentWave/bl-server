<?php

namespace App\Services;

use App\Exceptions\ClientPhoneNumberInvalidException;
use App\Exceptions\DeviceNotBoundException;
use App\Exceptions\DuplicateReportException;
use App\Exceptions\SignatureInvalidException;
use App\Models\Client;
use App\Models\ClientFeatureLevel;
use App\Models\DeviceBinding;
use App\Models\Report;
use Illuminate\Support\Facades\DB;

class ReportSubmissionService
{
    public function __construct(
        private readonly DeviceSignatureService $deviceSignatureService,
        private readonly EscortPhoneNumberNormalizer $phoneNumberNormalizer,
    ) {}

    public function submit(string $clientPhoneNumber, string $feature, string $publicKey, string $signature): array
    {
        $normalizedPublicKey = $this->deviceSignatureService->normalizePublicKey($publicKey);
        $deviceBinding = DeviceBinding::query()
            ->where('public_key', $normalizedPublicKey)
            ->first();

        if (! $deviceBinding) {
            throw DeviceNotBoundException::create();
        }

        $normalizedClientPhoneNumber = $this->phoneNumberNormalizer->normalizeE164($clientPhoneNumber);

        if ($normalizedClientPhoneNumber === null) {
            throw ClientPhoneNumberInvalidException::create();
        }

        if (! $this->deviceSignatureService->verifyReport(
            $normalizedClientPhoneNumber,
            $feature,
            $normalizedPublicKey,
            $signature,
        )) {
            throw SignatureInvalidException::create();
        }

        $clientHash = hash('sha256', $normalizedClientPhoneNumber);
        $reporterHash = hash('sha256', $deviceBinding->phone_number);
        $threshold = (int) config('reporting.threshold', 3);

        return DB::transaction(function () use ($clientHash, $reporterHash, $feature, $threshold): array {
            // A no-op upsert also serializes first creation without an early REPEATABLE-READ snapshot.
            DB::table('clients')->upsert([[
                'client_hash' => $clientHash,
                'created_at' => now(),
                'updated_at' => now(),
            ]], ['client_hash'], ['client_hash']);

            $client = Client::query()
                ->where('client_hash', $clientHash)
                ->lockForUpdate()
                ->firstOrFail();

            $alreadyReported = Report::query()
                ->where('client_id', $client->id)
                ->where('reporter_hash', $reporterHash)
                ->where('feature', $feature)
                ->lockForUpdate()
                ->first();

            if ($alreadyReported) {
                throw DuplicateReportException::create($feature);
            }

            Report::query()->create([
                'client_id' => $client->id,
                'reporter_hash' => $reporterHash,
                'feature' => $feature,
            ]);

            $uniqueReporterCount = Report::query()
                ->where('client_id', $client->id)
                ->where('feature', $feature)
                ->lockForUpdate()
                ->count();

            $levelTwo = $uniqueReporterCount >= $threshold;

            $featureLevel = ClientFeatureLevel::query()
                ->lockForUpdate()
                ->firstOrNew([
                    'client_id' => $client->id,
                    'feature' => $feature,
                ]);

            $featureLevel->unique_reporter_count = $uniqueReporterCount;
            $featureLevel->is_level_two = $levelTwo;

            if ($levelTwo && ! $featureLevel->promoted_at) {
                $featureLevel->promoted_at = now();
            }

            $featureLevel->save();

            return [
                'client_hash' => $clientHash,
                'reporter_hash' => $reporterHash,
                'feature' => $feature,
                'feature_label' => config('reporting.features.'.$feature, $feature),
                'unique_reporter_count' => $uniqueReporterCount,
                'level' => $levelTwo ? 'level_2' : 'level_1',
                'ready_for_sync' => $levelTwo,
            ];
        }, 3);
    }
}
