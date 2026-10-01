<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PasswordResetController extends Controller
{
    public function requestForm(): View
    {
        return view('auth.forgot-password');
    }

    public function sendLink(Request $request): RedirectResponse
    {
        $data = $request->validate(['email' => 'required|email|max:190']);
        $email = mb_strtolower(trim($data['email']));
        $user = User::query()->whereRaw('LOWER(email) = ?', [$email])->whereNull('deleted_at')->first();
        if ($user) {
            $plainToken = Str::random(64);
            DB::table('password_reset_tokens')->updateOrInsert(
                ['email' => $email],
                ['token' => hash('sha256', $plainToken), 'created_at' => now()]
            );
            $url = url('/reset-password/'.$plainToken).'?email='.urlencode($email);
            try {
                Mail::raw("A password reset was requested for your Virtue Visa CRM account.\n\nReset your password: {$url}\n\nThis link expires in 60 minutes. If you did not request it, ignore this email.", function ($message) use ($email): void {
                    $message->to($email)->subject('Virtue Visa CRM password reset');
                });
            } catch (\Throwable $exception) {
                report($exception);
            }
        }

        return back()->with('success', 'If the account exists, a password reset link has been sent.');
    }

    public function resetForm(Request $request, string $token): View
    {
        return view('auth.reset-password', ['token' => $token, 'email' => (string) $request->query('email', '')]);
    }

    public function reset(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => 'required|email|max:190',
            'token' => 'required|string|size:64',
            'password' => 'required|string|min:12|confirmed',
        ]);
        $email = mb_strtolower(trim($data['email']));
        $record = DB::table('password_reset_tokens')
            ->where('email', $email)
            ->where('token', hash('sha256', $data['token']))
            ->where('created_at', '>=', now()->subMinutes(60))
            ->first();
        if (!$record) {
            return back()->withInput($request->except('password', 'password_confirmation', 'token'))->withErrors(['email' => 'This password reset link is invalid or expired.']);
        }
        $user = User::query()->whereRaw('LOWER(email) = ?', [$email])->whereNull('deleted_at')->firstOrFail();
        $user->forceFill(['password' => Hash::make($data['password'])])->save();
        DB::table('password_reset_tokens')->where('email', $email)->delete();
        AuditLog::query()->create([
            'tenant_id' => $user->tenant_id,
            'user_id' => $user->id,
            'action' => 'auth.password_reset',
            'entity_type' => 'user',
            'entity_id' => $user->id,
            'diff_json' => null,
            'ip_address' => $request->ip(),
        ]);

        return redirect('/login')->with('success', 'Password changed. You can now sign in.');
    }
}
