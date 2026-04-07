<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['client_id', 'reporter_hash', 'feature'])]
class Report extends Model
{
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
