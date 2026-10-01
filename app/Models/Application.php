<?php

namespace App\Models;

use App\Models\Concerns\TenantScoped;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class Application extends Model
{
    use TenantScoped;

    protected $fillable = [
        'tenant_id',
        'student_id',
        'university_id',
        'program',
        'intake',
        'status',
        'deadline',
        'next_followup_at',
        'notes',
        'enroll_probability',
        'best_next_action',
        'explainability',
        'last_activity_at',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function university(): BelongsTo
    {
        return $this->belongsTo(University::class);
    }
}
