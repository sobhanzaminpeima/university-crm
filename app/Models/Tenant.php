<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tenant extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'subdomain',
        'custom_domain',
        'access_url',
        'country',
        'currency',
        'is_active',
        'owner_name',
        'email',
        'phone',
        'plan_type',
        'subscription_start_date',
        'subscription_end_date',
        'subscription_status',
        'billing_cycle',
    ];

    protected $casts = [
        'subscription_start_date' => 'date',
        'subscription_end_date' => 'date',
    ];
}
