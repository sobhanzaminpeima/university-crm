<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Feature extends Model
{
    protected $fillable = ['key', 'name'];

    public function tenantFeatures(): HasMany
    {
        return $this->hasMany(TenantFeature::class);
    }
}

