<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'driver',
    'endpoint_host',
    'endpoint_port',
    'target_url',
    'target_host',
    'method',
    'http_status',
    'outcome',
    'latency_ms',
    'error_type',
    'error_message',
    'response_preview',
    'attempted_at',
])]
class ScraperProxyAttempt extends Model
{
    protected function casts(): array
    {
        return [
            'attempted_at' => 'datetime',
        ];
    }
}
