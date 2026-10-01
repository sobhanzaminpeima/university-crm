<?php

namespace App\Models;

use App\Models\Concerns\TenantScoped;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;

class Student extends Model
{
    use TenantScoped;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'agent_id',
        'sub_agent_id',
        'full_name',
        'email',
        'phone',
        'nationality',
        'gpa',
        'field_of_study',
        'preferred_university_language',
        'english_level',
        'target_country',
        'lead_source',
        'budget_usd',
        'passport_number',
        'stage',
        'lifecycle_stage',
        'stage_temperature',
        'is_active',
        'deleted_at',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    public function subAgent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sub_agent_id');
    }

    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }
}
