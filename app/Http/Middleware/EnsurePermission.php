<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class EnsurePermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->attributes->get('auth_user');
        if (!$user) {
            return redirect('/login');
        }

        if ($user->role_slug === 'super_admin') {
            return $next($request);
        }

        if (!$user->hasPermission($permission)) {
            Log::warning('crm.permission_denied', [
                'tenant_id' => $user->tenant_id,
                'user_id' => $user->id,
                'role_slug' => $user->role_slug,
                'permission' => $permission,
                'path' => $request->path(),
                'method' => $request->method(),
            ]);
            abort(403, 'You do not have permission for this action.');
        }

        return $next($request);
    }
}
