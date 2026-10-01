<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantSubscriptionActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->attributes->get('auth_user');
        if (!$user || $user->role_slug === 'super_admin') {
            return $next($request);
        }

        $tenant = Tenant::query()->find($user->tenant_id);
        if (!$tenant) {
            abort(403, 'Tenant account was not found.');
        }
        if ((int) ($tenant->is_active ?? 1) !== 1) {
            abort(403, 'Tenant account is suspended.');
        }
        if (!empty($tenant->subscription_end_date) && now()->greaterThan($tenant->subscription_end_date)) {
            abort(403, 'Tenant subscription is expired.');
        }

        return $next($request);
    }
}

