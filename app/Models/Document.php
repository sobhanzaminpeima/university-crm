<?php

namespace App\Models;

use App\Models\Concerns\TenantScoped;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class Document extends Model
{
    use TenantScoped;

    protected $fillable = [
        'tenant_id',
        'student_id',
        'type',
        'file_url',
        'file_name',
        'status',
        'review_note',
        'expiry_date',
        'ocr_json',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
