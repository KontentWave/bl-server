<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['phone_number', 'public_key', 'verified_at', 'enrollment_id', 'enrollment_kind', 'ad_url',
    'lifecycle_state', 'lifecycle_version', 'last_renewed_at', 'next_renewal_at',
    'offboarded_at', 'offboarding_reason', 'fetch_window_at', 'fetch_count', 'renewal_failures', 'retry_at'])]
class DeviceBinding extends Model
{
    protected function casts(): array
    {
        return [
            'verified_at' => 'datetime',
            'last_renewed_at' => 'datetime',
            'next_renewal_at' => 'datetime',
            'offboarded_at' => 'datetime',
            'fetch_window_at' => 'datetime',
            'retry_at' => 'datetime',
            'lifecycle_version' => 'integer',
            'fetch_count' => 'integer',
            'renewal_failures' => 'integer',
        ];
    }
}
