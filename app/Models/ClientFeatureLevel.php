<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['client_id', 'feature', 'unique_reporter_count', 'is_level_two', 'promoted_at'])]
class ClientFeatureLevel extends Model
{
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    protected function casts(): array
    {
        return [
            'is_level_two' => 'boolean',
            'promoted_at' => 'datetime',
        ];
    }
}
