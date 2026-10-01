<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use App\Support\SecretValue;

class SettingsController extends Controller
{
    public function index(Request $request): View
    {
        $user = $this->authUser($request);
        if (Schema::hasTable('currencies')) {
            $this->ensureDefaultCurrencies($user->tenant_id);
        }
        $this->ensureNotificationSettings($user->tenant_id);
        $currencies = collect();
        if (Schema::hasTable('currencies')) {
            $currencies = DB::table('currencies')
                ->where('tenant_id', $user->tenant_id)
                ->where('is_active', 1)
                ->orderByDesc('is_default')
                ->orderBy('code')
                ->get();
        }
        $whatsappSettings = null;
        if (Schema::hasTable('tenant_notification_settings')) {
            $whatsappSettings = DB::table('tenant_notification_settings')
                ->where('tenant_id', $user->tenant_id)
                ->first();
        }
        $integrationSettings = null;
        if (Schema::hasTable('tenant_integration_settings')) {
            $integrationSettings = DB::table('tenant_integration_settings')
                ->where('tenant_id', $user->tenant_id)
                ->first();
        }
        $sourceCosts = collect();
        if (Schema::hasTable('lead_source_costs')) {
            $sourceCosts = DB::table('lead_source_costs')
                ->where('tenant_id', $user->tenant_id)
                ->orderBy('source_key')
                ->get();
        }
        $whatsappFeatureEnabled = $this->isFeatureEnabledForTenant($user->tenant_id, 'whatsapp_notifications');

        return view('settings.index', [
            'user' => $user,
            'currencies' => $currencies,
            'whatsappSettings' => $whatsappSettings,
            'integrationSettings' => $integrationSettings,
            'sourceCosts' => $sourceCosts,
            'whatsappFeatureEnabled' => $whatsappFeatureEnabled,
            'currentTheme' => (string) $request->cookie('theme', 'figma-light'),
        ]);
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $user = $this->authUser($request);
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'language' => 'required|string|in:en,tr,fa',
            'font_scale' => 'nullable|string|in:sm,base,lg',
            'currency_preference' => 'nullable|string|max:10',
            'theme' => 'nullable|string|in:figma-light,figma-dark,classic-light,classic-dark',
        ]);
        $theme = (string) ($data['theme'] ?? 'figma-light');
        unset($data['theme']);
        User::query()->where('id', $user->id)->update($data);
        $this->audit($request, 'settings.profile.update', 'user', $user->id, array_merge($data, [
            'theme' => $theme,
        ]));

        $fontScale = (string) ($data['font_scale'] ?? 'base');

        return back()
            ->withCookie(cookie('theme', $theme, 60 * 24 * 365, '/', null, false, false, false, 'lax'))
            ->withCookie(cookie('font_scale', $fontScale, 60 * 24 * 365, '/', null, false, false, false, 'lax'))
            ->with('success', 'Profile updated.');
    }

    public function addCurrency(Request $request): RedirectResponse
    {
        $user = $this->authUser($request);
        if (!Schema::hasTable('currencies')) {
            return back()->withErrors(['currencies' => 'Currencies table is missing. Run migration or import SQL patch first.']);
        }
        $data = $request->validate([
            'code' => 'required|string|max:10',
            'name' => 'required|string|max:80',
            'symbol' => 'nullable|string|max:12',
            'is_default' => 'nullable|boolean',
        ]);
        $code = strtoupper(trim((string) $data['code']));

        DB::transaction(function () use ($user, $data, $code): void {
            if ((int) ($data['is_default'] ?? 0) === 1) {
                DB::table('currencies')->where('tenant_id', $user->tenant_id)->update(['is_default' => 0]);
            }
            DB::table('currencies')->updateOrInsert(
                ['tenant_id' => $user->tenant_id, 'code' => $code],
                [
                    'name' => trim((string) $data['name']),
                    'symbol' => $data['symbol'] ?? null,
                    'is_default' => (int) ($data['is_default'] ?? 0),
                    'is_active' => 1,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        });

        return back()->with('success', 'Currency saved.');
    }

    public function changePassword(Request $request): RedirectResponse
    {
        $user = $this->authUser($request);
        $data = $request->validate([
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8|confirmed',
        ]);
        if (!Hash::check($data['current_password'], $user->password)) {
            return back()->withErrors(['current_password' => 'Current password is not valid']);
        }

        User::query()->where('id', $user->id)->update(['password' => Hash::make($data['new_password'])]);
        $this->audit($request, 'settings.password.update', 'user', $user->id);

        return back()->with('success', 'Password changed.');
    }

    public function updateWhatsapp(Request $request): RedirectResponse
    {
        $user = $this->authUser($request);
        if (!Schema::hasTable('tenant_notification_settings')) {
            return back()->withErrors(['whatsapp' => 'Notification settings table is missing. Run migration or SQL patch first.']);
        }
        if (!$this->isFeatureEnabledForTenant($user->tenant_id, 'whatsapp_notifications')) {
            return back()->withErrors(['whatsapp' => 'WhatsApp notifications are disabled for this SaaS tenant.']);
        }
        $data = $request->validate([
            'whatsapp_enabled' => 'nullable|boolean',
            'whatsapp_number' => 'nullable|string|max:30',
            'provider' => 'nullable|string|in:custom,twilio',
            'api_url' => 'nullable|url|max:255',
            'api_token' => 'nullable|string|max:255',
            'notify_new_student' => 'nullable|boolean',
            'notify_application_update' => 'nullable|boolean',
            'notify_document_update' => 'nullable|boolean',
            'docs_pending_days' => 'nullable|integer|min:1|max:30',
            'docs_pending_task_enabled' => 'nullable|boolean',
            'docs_pending_email_enabled' => 'nullable|boolean',
            'docs_pending_whatsapp_enabled' => 'nullable|boolean',
            'docs_pending_sms_enabled' => 'nullable|boolean',
        ]);

        $whatsappValues = [
                'whatsapp_enabled' => (int) ($data['whatsapp_enabled'] ?? 0),
                'whatsapp_number' => $data['whatsapp_number'] ?: null,
                'provider' => $data['provider'] ?: 'custom',
                'api_url' => $data['api_url'] ?: null,
                'notify_new_student' => (int) ($data['notify_new_student'] ?? 0),
                'notify_application_update' => (int) ($data['notify_application_update'] ?? 0),
                'notify_document_update' => (int) ($data['notify_document_update'] ?? 0),
                'docs_pending_days' => (int) ($data['docs_pending_days'] ?? 3),
                'docs_pending_task_enabled' => (int) ($data['docs_pending_task_enabled'] ?? 0),
                'docs_pending_email_enabled' => (int) ($data['docs_pending_email_enabled'] ?? 0),
                'docs_pending_whatsapp_enabled' => (int) ($data['docs_pending_whatsapp_enabled'] ?? 0),
                'docs_pending_sms_enabled' => (int) ($data['docs_pending_sms_enabled'] ?? 0),
                'updated_at' => now(),
                'created_at' => now(),
        ];
        if (!empty($data['api_token'])) {
            $whatsappValues['api_token'] = SecretValue::encrypt($data['api_token']);
        }
        DB::table('tenant_notification_settings')->updateOrInsert(
            ['tenant_id' => $user->tenant_id],
            $whatsappValues
        );

        $this->audit($request, 'settings.whatsapp.update', 'tenant', $user->tenant_id, [
            'enabled' => (int) ($data['whatsapp_enabled'] ?? 0),
            'provider' => $data['provider'] ?? 'custom',
        ]);

        return back()->with('success', 'WhatsApp settings updated.');
    }

    public function updateIntegrations(Request $request): RedirectResponse
    {
        $user = $this->authUser($request);
        if (!Schema::hasTable('tenant_integration_settings')) {
            return back()->withErrors(['integrations' => 'Integration settings table is missing. Run migration or SQL patch first.']);
        }
        $data = $request->validate([
            'email_enabled' => 'nullable|boolean',
            'email_from_address' => 'nullable|email|max:190',
            'email_from_name' => 'nullable|string|max:120',
            'sms_enabled' => 'nullable|boolean',
            'sms_api_url' => 'nullable|url|max:255',
            'sms_api_token' => 'nullable|string|max:255',
            'ai_enabled' => 'nullable|boolean',
            'ai_provider' => 'nullable|string|in:openai,custom',
            'ai_model' => 'nullable|string|max:80',
            'ai_api_key' => 'nullable|string|max:255',
        ]);

        $integrationValues = [
                'email_enabled' => (int) ($data['email_enabled'] ?? 0),
                'email_from_address' => $data['email_from_address'] ?? null,
                'email_from_name' => $data['email_from_name'] ?? null,
                'sms_enabled' => (int) ($data['sms_enabled'] ?? 0),
                'sms_api_url' => $data['sms_api_url'] ?? null,
                'ai_enabled' => (int) ($data['ai_enabled'] ?? 0),
                'ai_provider' => $data['ai_provider'] ?? 'openai',
                'ai_model' => $data['ai_model'] ?? null,
                'updated_at' => now(),
                'created_at' => now(),
        ];
        if (!empty($data['sms_api_token'])) {
            $integrationValues['sms_api_token'] = SecretValue::encrypt($data['sms_api_token']);
        }
        if (!empty($data['ai_api_key'])) {
            $integrationValues['ai_api_key'] = SecretValue::encrypt($data['ai_api_key']);
        }
        DB::table('tenant_integration_settings')->updateOrInsert(
            ['tenant_id' => $user->tenant_id],
            $integrationValues
        );

        $this->audit($request, 'settings.integrations.update', 'tenant', $user->tenant_id, [
            'email_enabled' => (int) ($data['email_enabled'] ?? 0),
            'sms_enabled' => (int) ($data['sms_enabled'] ?? 0),
            'ai_enabled' => (int) ($data['ai_enabled'] ?? 0),
        ]);

        return back()->with('success', 'Integration settings updated.');
    }

    public function updateLeadSourceCosts(Request $request): RedirectResponse
    {
        $user = $this->authUser($request);
        if (!Schema::hasTable('lead_source_costs')) {
            return back()->withErrors(['lead_source_costs' => 'Lead source costs table is missing. Run migration or SQL patch first.']);
        }
        $data = $request->validate([
            'source_key' => 'required|string|max:60',
            'monthly_cost' => 'required|numeric|min:0',
        ]);

        DB::table('lead_source_costs')->updateOrInsert(
            ['tenant_id' => $user->tenant_id, 'source_key' => trim((string) $data['source_key'])],
            [
                'monthly_cost' => (float) $data['monthly_cost'],
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        return back()->with('success', 'Lead source cost updated.');
    }

    private function ensureDefaultCurrencies(int $tenantId): void
    {
        if (!Schema::hasTable('currencies')) {
            return;
        }
        $exists = DB::table('currencies')->where('tenant_id', $tenantId)->exists();
        if ($exists) {
            return;
        }

        $now = now();
        DB::table('currencies')->insert([
            ['tenant_id' => $tenantId, 'code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$', 'is_default' => 1, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['tenant_id' => $tenantId, 'code' => 'EUR', 'name' => 'Euro', 'symbol' => '€', 'is_default' => 0, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['tenant_id' => $tenantId, 'code' => 'GBP', 'name' => 'Pound Sterling', 'symbol' => '£', 'is_default' => 0, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['tenant_id' => $tenantId, 'code' => 'TRY', 'name' => 'Turkish Lira', 'symbol' => '₺', 'is_default' => 0, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    private function ensureNotificationSettings(int $tenantId): void
    {
        if (!Schema::hasTable('tenant_notification_settings')) {
            return;
        }
        DB::table('tenant_notification_settings')->updateOrInsert(
            ['tenant_id' => $tenantId],
            [
                'whatsapp_enabled' => 0,
                'provider' => 'custom',
                'notify_new_student' => 0,
                'notify_application_update' => 0,
                'notify_document_update' => 0,
                'docs_pending_days' => 3,
                'docs_pending_task_enabled' => 1,
                'docs_pending_email_enabled' => 0,
                'docs_pending_whatsapp_enabled' => 1,
                'docs_pending_sms_enabled' => 0,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }

    private function isFeatureEnabledForTenant(int $tenantId, string $featureKey): bool
    {
        if (!DB::getSchemaBuilder()->hasTable('features') || !DB::getSchemaBuilder()->hasTable('tenant_features')) {
            return true;
        }
        $featureId = DB::table('features')->where('key', $featureKey)->value('id');
        if (!$featureId) {
            return true;
        }
        return (bool) DB::table('tenant_features')
            ->where('tenant_id', $tenantId)
            ->where('feature_id', $featureId)
            ->value('is_enabled');
    }
}
