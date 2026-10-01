<?php

namespace App\Models;

use App\Models\Concerns\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class StudentMessage extends Model
{
    use TenantScoped;

    protected $fillable = [
        'tenant_id',
        'student_id',
        'student_user_id',
        'recipient_user_id',
        'sender_role',
        'body',
        'attachment_url',
        'attachment_name',
        'read_at',
    ];
}
