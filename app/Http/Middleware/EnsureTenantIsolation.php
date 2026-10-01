<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantIsolation
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->attributes->get('auth_user');
        if (!$user) {
            return redirect('/login');
        }

        // Non-super admins must stay inside own tenant.
        if ($user->role_slug !== 'super_admin') {
            app()->instance('current_tenant_id', $user->tenant_id);
            $tenant = Tenant::query()->find($user->tenant_id);
            if ($tenant && !empty($tenant->subdomain)) {
                $host = preg_replace('/^www\./', '', $request->getHost()) ?: $request->getHost();
                $customDomain = trim((string) ($tenant->custom_domain ?? ''));
                $appHost = parse_url((string) config('app.url'), PHP_URL_HOST);
                $appHost = $appHost ? (preg_replace('/^www\./', '', $appHost) ?: $appHost) : '';
                $baseDomain = trim((string) config('app.saas_base_domain', ''));
                $isTenantHost = str_starts_with($host, ((string) $tenant->subdomain).'.')
                    || ($customDomain !== '' && $host === $customDomain);
                $isBaseHost = ($appHost !== '' && $host === $appHost)
                    || ($baseDomain !== '' && $host === $baseDomain);
                $isLocalHost = in_array($host, ['localhost', '127.0.0.1'], true) || str_ends_with($host, '.test');
                if (!$isTenantHost && !$isBaseHost && !$isLocalHost) {
                    abort(403, 'This account must be accessed from its assigned tenant subdomain.');
                }
            }
        }

        return $next($request);
    }
}
