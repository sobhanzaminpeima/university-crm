<?php

namespace App\Models;

use App\Models\Concerns\TenantScoped;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;

class University extends Model
{
    use TenantScoped;

    protected $fillable = [
        'tenant_id',
        'created_by_user_id',
        'name',
        'country',
        'city',
        'institution_type',
        'website',
        'logo_url',
        'image_url',
        'currency',
        'tuition_range',
        'language',
        'programs_summary',
        'deadline',
        'visa_notes',
        'description',
        'is_active',
    ];

    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }

    public function scholarships(): HasMany
    {
        return $this->hasMany(Scholarship::class);
    }
}
