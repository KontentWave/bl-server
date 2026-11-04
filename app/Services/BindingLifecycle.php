<?php

namespace App\Services;

use App\Exceptions\ApiDomainException;
use App\Models\DeviceBinding;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BindingLifecycle
{
    public function withSelection(string $publicKey, Closure $callback): mixed
    {
        return DB::transaction(function () use ($publicKey, $callback) {
            $hash = hash('sha256', $publicKey);
            DB::table('device_key_selections')->upsert(
                [['key_hash' => $hash, 'binding_id' => null]], ['key_hash'], ['key_hash'],
            );
            $selection = DB::table('device_key_selections')->where('key_hash', $hash)->lockForUpdate()->first();
            $bindings = DeviceBinding::where('public_key', $publicKey)->orderBy('id')->lockForUpdate()->get();
            $binding = $selection->binding_id === null
                ? $bindings->first()
                : $bindings->firstWhere('id', $selection->binding_id);

            return $callback($binding, $bindings, $hash, $selection->binding_id !== null);
        }, 3);
    }

    public function initializeLegacy(DeviceBinding $binding): void
    {
        if ($binding->enrollment_id !== null) {
            return;
        }
        $binding->fill([
            'enrollment_id' => (string) Str::uuid(),
            'enrollment_kind' => 'legacy', 'lifecycle_state' => 'active', 'lifecycle_version' => 1,
        ])->save();
        $this->recordGeneration($binding);
    }

    public function recordGeneration(DeviceBinding $binding): void
    {
        DB::table('device_enrollment_generations')->insert([
            'enrollment_id' => $binding->enrollment_id, 'key_hash' => hash('sha256', $binding->public_key),
        ]);
    }

    public function snapshot(DeviceBinding $binding): array
    {
        return [
            'enrollment_id' => $binding->enrollment_id,
            'kind' => $binding->enrollment_kind ?? 'legacy',
            'state' => $binding->lifecycle_state ?? 'active',
            'version' => $binding->lifecycle_version ?? 1,
            'verified_at' => $binding->verified_at->toIso8601String(),
            'last_renewed_at' => $binding->last_renewed_at?->toIso8601String(),
            'next_renewal_at' => $binding->next_renewal_at?->toIso8601String(),
            'offboarded_at' => $binding->offboarded_at?->toIso8601String(),
            'reason' => $binding->offboarding_reason,
        ];
    }

    public function meta(DeviceBinding $binding, ?string $requestId = null): array
    {
        return ($requestId === null ? [] : ['request_id' => $requestId]) + [
            'server_time' => now()->toIso8601String(), 'lifecycle' => $this->snapshot($binding),
        ];
    }

    public function assertActive(DeviceBinding $binding, ?string $requestId = null): void
    {
        if ($binding->lifecycle_state === 'offboarded') {
            throw $this->error('device_offboarded', 403, 'authorization', $this->meta($binding, $requestId));
        }
    }

    public function error(string $code, int $status, string $field, array $meta = []): ApiDomainException
    {
        return new ApiDomainException($code, 'Device authorization could not be completed.', $status,
            [$field => ['Device authorization could not be completed.']], $meta + ['retryable' => false]);
    }

    public function authorize(string $publicKey, Closure $missing): DeviceBinding
    {
        return $this->withSelection($publicKey, function ($binding) use ($missing) {
            if (! $binding) {
                throw $missing();
            }
            $this->assertActive($binding);

            return $binding;
        });
    }
}
