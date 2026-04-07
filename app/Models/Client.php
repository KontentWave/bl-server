<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['client_hash'])]
class Client extends Model
{
    public function reports(): HasMany
    {
        return $this->hasMany(Report::class);
    }

    public function featureLevels(): HasMany
    {
        return $this->hasMany(ClientFeatureLevel::class);
    }
}
