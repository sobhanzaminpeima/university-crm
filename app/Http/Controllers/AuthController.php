<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showLogin(): View
    {
        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'login' => ['nullable', 'string', 'max:190'],
            'email' => ['nullable', 'string', 'max:190'],
            'password' => ['required', 'string'],
        ]);
        $login = trim((string) ($data['login'] ?? $data['email'] ?? ''));
        if ($login === '') {
            return back()->withErrors(['login' => 'Login is required'])->withInput();
        }
        $rateKey = 'crm-login:'.sha1(mb_strtolower($login).'|'.$request->ip());
        if (RateLimiter::tooManyAttempts($rateKey, 5)) {
            return back()->withErrors(['login' => 'Too many login attempts. Try again in '.RateLimiter::availableIn($rateKey).' seconds.'])->withInput();
        }

        try {
            $user = User::query()
                ->where(function ($query) use ($login) {
                    $query->whereRaw('LOWER(email) = ?', [mb_strtolower($login)])
                        ->orWhere('name', $login);
                })
                ->whereNull('deleted_at')
                ->first();
        } catch (QueryException $e) {
            report($e);
            return back()
                ->withInput($request->except('password'))
                ->withErrors(['login' => 'Database connection failed. Please start MySQL and try again.']);
        }
        if (!$user || !$user->is_active || !Hash::check($data['password'], $user->password)) {
            RateLimiter::hit($rateKey, 300);
            if ($user) {
                AuditLog::query()->create([
                    'tenant_id' => $user->tenant_id,
                    'user_id' => $user->id,
                    'action' => 'auth.login_failed',
                    'entity_type' => 'user',
                    'entity_id' => $user->id,
                    'diff_json' => json_encode(['reason' => !$user->is_active ? 'inactive' : 'bad_password']),
                    'ip_address' => $request->ip(),
                ]);
            }
            return back()->withErrors(['login' => 'Invalid credentials'])->withInput();
        }
        if ($user->role_slug !== 'super_admin') {
            $tenant = Tenant::query()->find($user->tenant_id);
            if (!$tenant || (int) ($tenant->is_active ?? 1) !== 1 || (string) ($tenant->subscription_status ?? 'active') !== 'active') {
                return back()->withErrors(['login' => 'Your company account is pending approval or suspended.'])->withInput();
            }
        }
        Auth::guard('student')->logout();
        Auth::guard('crm')->logout();
        RateLimiter::clear($rateKey);

        if ($user->role_slug === 'student') {
            Auth::guard('student')->login($user, false);
            $request->session()->regenerate();
            $request->session()->regenerateToken();
            return redirect('/portal/dashboard');
        }

        Auth::guard('crm')->login($user, false);
        $request->session()->regenerate();
        $request->session()->regenerateToken();

        AuditLog::query()->create([
            'tenant_id' => $user->tenant_id,
            'user_id' => $user->id,
            'action' => 'auth.login',
            'entity_type' => 'user',
            'entity_id' => $user->id,
            'diff_json' => null,
            'ip_address' => $request->ip(),
        ]);

        if ($user->role_slug === 'super_admin') {
            return redirect('/saas/tenants');
        }
        return redirect('/dashboard');
    }

    public function logout(Request $request): RedirectResponse
    {
        $user = Auth::guard('crm')->user();
        if ($user) {
            AuditLog::query()->create([
                'tenant_id' => $user->tenant_id,
                'user_id' => $user->id,
                'action' => 'auth.logout',
                'entity_type' => 'user',
                'entity_id' => $user->id,
                'diff_json' => null,
                'ip_address' => $request->ip(),
            ]);
        }
        Auth::guard('crm')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect('/login');
    }
}
