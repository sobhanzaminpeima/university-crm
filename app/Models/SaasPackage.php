<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SaasPackage extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'price',
        'currency',
        'duration_months',
        'features_json',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'features_json' => 'array',
        'price' => 'decimal:2',
        'is_active' => 'boolean',
    ];
}

