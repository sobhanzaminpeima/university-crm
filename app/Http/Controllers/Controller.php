<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class Controller extends BaseController
{
    use AuthorizesRequests;
    use ValidatesRequests;

    protected function authUser(Request $request): User
    {
        return $request->attributes->get('auth_user');
    }

    protected function tenantId(Request $request): ?int
    {
        return $this->authUser($request)->tenant_id;
    }

    protected function perPage(Request $request, int $default = 50): int
    {
        $perPage = (int) $request->query('per_page', $default);

        return in_array($perPage, [15, 50, 100], true) ? $perPage : $default;
    }

    protected function audit(Request $request, string $action, string $entityType, int $entityId, array $diff = []): void
    {
        $user = $this->authUser($request);
        AuditLog::query()->create([
            'tenant_id' => $user->tenant_id,
            'user_id' => $user->id,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'diff_json' => json_encode($diff, JSON_UNESCAPED_UNICODE),
            'ip_address' => $request->ip(),
        ]);
    }

    protected function applyStudentOwnershipScope(EloquentBuilder|QueryBuilder $query, User $user, string $column = 'id'): void
    {
        if ($user->role_slug === 'agent') {
            $query->where("students.{$column}", '!=', 0)->where('students.agent_id', $user->id);
            return;
        }
        if ($user->role_slug === 'sub_agent') {
            $query->where("students.{$column}", '!=', 0)->where('students.sub_agent_id', $user->id);
        }
    }

    protected function enforceStudentOwnershipOrFail(User $user, int $studentId, string $context): void
    {
        if (!in_array($user->role_slug, ['agent', 'sub_agent'], true) || $user->hasPermission('students.view_all')) {
            return;
        }
        $query = Student::query()->where('tenant_id', $user->tenant_id)->where('id', $studentId)->whereNull('deleted_at');
        if ($user->role_slug === 'agent') {
            $query->where('agent_id', $user->id);
        } else {
            $query->where('sub_agent_id', $user->id);
        }
        if (!$query->exists()) {
            $this->logPermissionViolation($user, $context, ['student_id' => $studentId]);
            abort(403, 'Unauthorized student access.');
        }
    }

    protected function logPermissionViolation(User $user, string $context, array $meta = []): void
    {
        Log::warning('crm.permission_violation', array_merge([
            'tenant_id' => $user->tenant_id,
            'user_id' => $user->id,
            'role_slug' => $user->role_slug,
            'context' => $context,
        ], $meta));
    }
}
