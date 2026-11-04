<?php

namespace App\Services;

use App\Exceptions\ApiDomainException;
use App\Models\DeviceBinding;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AuthLifecycleService
{
    public function __construct(
        private readonly DeviceSignatureService $signatures,
        private readonly BindingLifecycle $lifecycle,
        private readonly RenewalProtection $protection,
        private readonly RenewalAdInspector $inspector,
    ) {}

    public function handle(string $operation, array $data, string $ip): array
    {
        $key = $this->signatures->normalizePublicKey($data['public_key']);
        $this->protection->requests($key, $ip);
        if (! $this->signatures->verifyLifecycle($operation, $data)) {
            throw $this->lifecycle->error('auth.renewal_unauthorized', 401, 'authorization');
        }
        DB::table('renewal_receipts')->where('created_at', '<=', now()->subHours(48))->delete();
        if ($operation === 'auth.status') {
            return $this->lifecycle->withSelection($key, function ($binding, $bindings, $hash, $selected) use ($data) {
                $this->checkSelection($binding, $bindings, $selected);
                $this->lifecycle->initializeLegacy($binding);
                $this->lifecycle->assertActive($binding, $data['request_id']);

                return $this->success('auth.status', $binding, $data['request_id']);
            });
        }

        return $this->protection->locked('enrollment:'.$data['enrollment_id'], function () use ($key, $data) {
            $digest = hash('sha256', $this->signatures->lifecyclePayload('auth.renew', $data));
            $attempt = $this->lifecycle->withSelection($key, function ($binding, $bindings, $hash, $selected) use ($data, $digest) {
                $this->checkSelection($binding, $bindings, $selected);
                $this->checkGeneration($binding, $data);
                $this->prune($binding->enrollment_id);
                $receipt = DB::table('renewal_receipts')->where('enrollment_id', $binding->enrollment_id)
                    ->where('request_id', $data['request_id'])->lockForUpdate()->first();
                if ($receipt && ! hash_equals($receipt->digest, $digest)) {
                    throw $this->lifecycle->error('auth.request_id_conflict', 409, 'request_id');
                }
                $this->lifecycle->assertActive($binding, $data['request_id']);
                if ($receipt) {
                    if ($receipt->outcome === 'pending') {
                        if (now()->lt(Carbon::parse($receipt->retry_at))) {
                            return ['response' => $this->throttled(1)];
                        }
                        $this->failPending($binding, $receipt);

                        return ['response' => $this->temporary($binding, $data['request_id'], $binding->retry_at)];
                    }

                    return ['response' => $receipt->outcome === 'auth.ad_status_unavailable'
                        ? $this->temporary($binding, $data['request_id'], Carbon::parse($receipt->retry_at))
                        : $this->success($receipt->outcome, $binding, $data['request_id'])];
                }
                if ($data['next_renewal_at'] !== $binding->next_renewal_at?->toIso8601String()) {
                    throw $this->lifecycle->error('auth.renewal_cycle_changed', 409, 'next_renewal_at',
                        $this->lifecycle->meta($binding, $data['request_id']));
                }
                if ($binding->enrollment_kind !== 'ad' || now()->lt($binding->next_renewal_at)) {
                    $outcome = $binding->enrollment_kind !== 'ad' ? 'auth.renewal_exempt' : 'auth.renewal_not_due';
                    $this->receipt($binding, $data, $digest, $outcome);

                    return ['response' => $this->success($outcome, $binding, $data['request_id'])];
                }
                $pending = DB::table('renewal_receipts')->where('enrollment_id', $binding->enrollment_id)
                    ->where('outcome', 'pending')->lockForUpdate()->first();
                if ($pending) {
                    if (now()->lt(Carbon::parse($pending->retry_at))) {
                        return ['response' => $this->throttled(1)];
                    }
                    $this->failPending($binding, $pending);

                    return ['response' => $this->throttled($binding->retry_at->timestamp - now()->timestamp)];
                }
                if ($binding->retry_at && now()->lt($binding->retry_at)) {
                    $this->protection->denied($binding->retry_at->timestamp - now()->timestamp);
                }
                if ($binding->fetch_window_at === null || now()->timestamp >= $binding->fetch_window_at->timestamp + 86400) {
                    $binding->fetch_window_at = now();
                    $binding->fetch_count = 0;
                }
                if ($binding->fetch_count >= 4) {
                    $this->protection->denied($binding->fetch_window_at->timestamp + 86400 - now()->timestamp);
                }
                $this->protection->fetch();
                $binding->fetch_count++;
                $binding->save();

                $receiptId = $this->receipt($binding, $data, $digest, 'pending', now()->addSeconds($this->protection->leaseSeconds()));

                return ['binding' => $binding, 'version' => $binding->lifecycle_version, 'receipt_id' => $receiptId];
            });
            if (isset($attempt['response'])) {
                return $attempt['response'];
            }
            $old = $attempt['binding'];
            $result = $this->inspector->inspect($old->ad_url, $old->phone_number);
            $response = $this->lifecycle->withSelection($key, function ($binding, $bindings, $hash, $selected) use ($data, $digest, $attempt, $result, $old) {
                $this->checkSelection($binding, $bindings, $selected);
                $this->checkGeneration($binding, $data);
                $this->lifecycle->assertActive($binding, $data['request_id']);
                if ($binding->id !== $old->id || $binding->lifecycle_version !== $attempt['version']) {
                    throw $this->lifecycle->error('auth.renewal_cycle_changed', 409, 'next_renewal_at',
                        $this->lifecycle->meta($binding, $data['request_id']));
                }
                $receipt = DB::table('renewal_receipts')->where('id', $attempt['receipt_id'])
                    ->where('enrollment_id', $binding->enrollment_id)->where('request_id', $data['request_id'])
                    ->where('digest', $digest)->lockForUpdate()->first();
                if (! $receipt) {
                    return $this->temporary($binding, $data['request_id'], now()->addSecond());
                }
                if ($receipt->outcome !== 'pending') {
                    return $this->temporary($binding, $data['request_id'], Carbon::parse($receipt->retry_at));
                }
                if (now()->gte(Carbon::parse($receipt->retry_at)) || $result['status'] === 'unknown') {
                    $this->failPending($binding, $receipt, $result['retry_after'] ?? 0);

                    return $this->temporary($binding, $data['request_id'], $binding->retry_at);
                }
                $binding->lifecycle_version++;
                $binding->renewal_failures = 0;
                $binding->retry_at = null;
                if ($result['status'] === 'active') {
                    $commitTime = now();
                    $binding->last_renewed_at = $commitTime;
                    $binding->next_renewal_at = $commitTime->copy()->addSeconds(86400);
                    $binding->save();
                    $this->completeReceipt($receipt, 'auth.renewed');

                    return $this->success('auth.renewed', $binding, $data['request_id']);
                }
                $binding->lifecycle_state = 'offboarded';
                $binding->offboarding_reason = $result['status'];
                $binding->offboarded_at = now();
                $binding->next_renewal_at = null;
                $binding->save();
                $this->completeReceipt($receipt, 'device_offboarded');

                return ['error' => $this->lifecycle->error('device_offboarded', 403, 'authorization',
                    $this->lifecycle->meta($binding, $data['request_id']))];
            });

            return $response;
        });
    }

    private function checkSelection(?DeviceBinding $binding, $bindings, bool $selected): void
    {
        if (! $binding) {
            throw $this->lifecycle->error('auth.renewal_unauthorized', 401, 'authorization');
        }
        if (! $selected && $bindings->count() > 1) {
            throw $this->lifecycle->error('auth.enrollment_ambiguous', 409, 'authorization');
        }
    }

    private function checkGeneration(DeviceBinding $binding, array $data): void
    {
        if ($binding->enrollment_id !== $data['enrollment_id'] || $binding->lifecycle_state === 'superseded') {
            $generation = DB::table('device_enrollment_generations')->where('enrollment_id', $data['enrollment_id'])->first();
            if (! $generation || ! hash_equals($generation->key_hash, hash('sha256', $binding->public_key))) {
                throw $this->lifecycle->error('auth.renewal_unauthorized', 401, 'authorization');
            }
            throw $this->lifecycle->error('auth.enrollment_changed', 409, 'enrollment_id');
        }
    }

    private function success(string $code, DeviceBinding $binding, string $id): array
    {
        return ['code' => $code, 'data' => $this->lifecycle->meta($binding, $id)];
    }

    private function temporary(DeviceBinding $binding, string $id, Carbon $retryAt): array
    {
        $delay = max(1, $retryAt->timestamp - now()->timestamp);

        return ['error' => new ApiDomainException('auth.ad_status_unavailable',
            'Ad status is temporarily unavailable.', 503, ['authorization' => ['Ad status is temporarily unavailable.']],
            $this->lifecycle->meta($binding, $id) + ['retryable' => true, 'retry_after' => $delay])];
    }

    private function prune(string $enrollment): void
    {
        $keep = DB::table('renewal_receipts')->where('enrollment_id', $enrollment)->orderByDesc('id')->limit(8)->pluck('id');
        DB::table('renewal_receipts')->where('enrollment_id', $enrollment)->whereNotIn('id', $keep)->delete();
    }

    private function receipt(DeviceBinding $binding, array $data, string $digest, string $outcome, ?Carbon $retryAt = null): int
    {
        $id = DB::table('renewal_receipts')->insertGetId([
            'enrollment_id' => $binding->enrollment_id, 'request_id' => $data['request_id'],
            'digest' => $digest, 'outcome' => $outcome, 'retry_at' => $retryAt, 'created_at' => now(),
        ]);
        $this->prune($binding->enrollment_id);

        return $id;
    }

    private function failPending(DeviceBinding $binding, object $receipt, int $retryAfter = 0): void
    {
        $delay = [900, 3600, 21600][min($binding->renewal_failures, 2)];
        $binding->renewal_failures = min(3, $binding->renewal_failures + 1);
        $binding->retry_at = now()->addSeconds(max($delay, $retryAfter));
        $binding->save();
        $this->completeReceipt($receipt, 'auth.ad_status_unavailable', $binding->retry_at);
    }

    private function completeReceipt(object $receipt, string $outcome, ?Carbon $retryAt = null): void
    {
        DB::table('renewal_receipts')->where('id', $receipt->id)->where('outcome', 'pending')
            ->update(['outcome' => $outcome, 'retry_at' => $retryAt]);
    }

    private function throttled(int $delay): array
    {
        return ['error' => new ThrottleRequestsException('Too many requests.', headers: ['Retry-After' => max(1, $delay)])];
    }
}
