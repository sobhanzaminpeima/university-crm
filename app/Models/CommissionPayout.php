<?php

namespace App\Models;

use App\Models\Concerns\TenantScoped;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class CommissionPayout extends Model
{
    use TenantScoped;

    protected $fillable = [
        'tenant_id',
        'agent_id',
        'currency',
        'amount',
        'note',
        'created_by_user_id',
        'paid_at',
    ];

    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agent_id');
    }
}
