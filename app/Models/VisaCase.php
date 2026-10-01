<?php

namespace App\Models;

use App\Models\Concerns\TenantScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VisaCase extends Model
{
    use TenantScoped;

    protected $fillable = [
        'tenant_id',
        'application_id',
        'student_id',
        'visa_type',
        'submission_date',
        'embassy_appointment_date',
        'interview_date',
        'decision',
        'decision_date',
        'notes',
    ];

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }
}
