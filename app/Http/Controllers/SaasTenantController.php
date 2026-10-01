<?php

namespace App\Http\Controllers;

use App\Models\Feature;
use App\Models\Tenant;
use App\Models\TenantFeature;
use App\Models\TenantSubscription;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SaasTenantController extends Controller
{
    public function index(Request $request): View
    {
        $auth = $this->authUser($request);
        $this->ensureSuperAdmin($auth->role_slug);

        $tenants = Tenant::query()->latest('id')->paginate(20);
        $features = Feature::query()->orderBy('name')->get();
        $tenantFeatures = TenantFeature::query()
            ->whereIn('tenant_id', $tenants->pluck('id'))
            ->get()
            ->groupBy('tenant_id')
            ->map(fn ($rows) => $rows->keyBy('feature_id'));

        return view('saas.tenants', compact('tenants', 'features', 'tenantFeatures'));
    }

    public function store(Request $request): RedirectResponse
    {
        $auth = $this->authUser($request);
        $this->ensureSuperAdmin($auth->role_slug);

        $data = $request->validate([
            'name' => 'required|string|max:190',
            'subdomain' => ['nullable', 'string', 'max:80', 'regex:/^[a-z0-9][a-z0-9-]*[a-z0-9]$/', Rule::unique('tenants', 'subdomain')],
            'custom_domain' => 'nullable|string|max:190',
            'owner_name' => 'nullable|string|max:190',
            'email' => 'nullable|email|max:190',
            'phone' => 'nullable|string|max:60',
            'currency' => 'nullable|string|max:10',
            'plan_type' => ['required', Rule::in(['monthly', '3_months', '6_months', '1_year'])],
            'subscription_start_date' => 'required|date',
            'subscription_end_date' => 'required|date|after_or_equal:subscription_start_date',
            'subscription_status' => ['required', Rule::in(['active', 'expired', 'suspended'])],
            'is_active' => 'required|boolean',
        ]);

        $data['slug'] = Str::slug((string) $data['name']).'-'.Str::lower(Str::random(4));
        $data['subdomain'] = $this->uniqueSubdomain((string) ($data['subdomain'] ?? $data['name']));
        $data['access_url'] = $this->tenantAccessUrl($request, $data['subdomain'], (string) ($data['custom_domain'] ?? ''));
        $data['billing_cycle'] = $data['plan_type'];
        $tenant = Tenant::query()->create($data);
        $this->audit($request, 'saas.tenant_create', 'tenant', $tenant->id, ['name' => $tenant->name, 'plan_type' => $tenant->plan_type]);

        foreach (Feature::query()->pluck('id') as $featureId) {
            TenantFeature::query()->create([
                'tenant_id' => $tenant->id,
                'feature_id' => $featureId,
                'is_enabled' => 1,
            ]);
        }

        return back()->with('success', 'Tenant created.');
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $auth = $this->authUser($request);
        $this->ensureSuperAdmin($auth->role_slug);
        $tenant = Tenant::query()->findOrFail($id);

        $data = $request->validate([
            'name' => 'required|string|max:190',
            'subdomain' => ['nullable', 'string', 'max:80', 'regex:/^[a-z0-9][a-z0-9-]*[a-z0-9]$/', Rule::unique('tenants', 'subdomain')->ignore($tenant->id)],
            'custom_domain' => 'nullable|string|max:190',
            'owner_name' => 'nullable|string|max:190',
            'email' => 'nullable|email|max:190',
            'phone' => 'nullable|string|max:60',
            'currency' => 'nullable|string|max:10',
            'plan_type' => ['required', Rule::in(['monthly', '3_months', '6_months', '1_year'])],
            'subscription_start_date' => 'required|date',
            'subscription_end_date' => 'required|date|after_or_equal:subscription_start_date',
            'subscription_status' => ['required', Rule::in(['active', 'expired', 'suspended'])],
            'is_active' => 'required|boolean',
        ]);

        $data['subdomain'] = $this->uniqueSubdomain((string) ($data['subdomain'] ?? $data['name']), $tenant->id);
        $data['access_url'] = $this->tenantAccessUrl($request, $data['subdomain'], (string) ($data['custom_domain'] ?? ''));
        $data['billing_cycle'] = $data['plan_type'];
        $tenant->update($data);
        if ((int) $tenant->is_active !== 1 || (string) $tenant->subscription_status !== 'active') {
            User::query()->where('tenant_id', $tenant->id)->where('role_slug', '!=', 'super_admin')->update(['is_active' => 0]);
        } else {
            User::query()->where('tenant_id', $tenant->id)->whereIn('role_slug', ['admin', 'agent', 'sub_agent', 'student'])->update(['is_active' => 1]);
        }
        $this->audit($request, 'saas.tenant_update', 'tenant', $tenant->id, ['status' => $data['subscription_status'], 'is_active' => $data['is_active']]);

        return back()->with('success', 'Tenant updated.');
    }

    public function updateFeatures(Request $request, int $id): RedirectResponse
    {
        $auth = $this->authUser($request);
        $this->ensureSuperAdmin($auth->role_slug);
        $tenant = Tenant::query()->findOrFail($id);

        $featureIds = Feature::query()->pluck('id')->all();
        $enabled = array_map('intval', $request->input('features', []));
        foreach ($featureIds as $featureId) {
            TenantFeature::query()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'feature_id' => $featureId],
                ['is_enabled' => in_array((int) $featureId, $enabled, true) ? 1 : 0]
            );
        }
        $this->audit($request, 'saas.tenant_features_update', 'tenant', $tenant->id, ['enabled' => $enabled]);

        return back()->with('success', 'Tenant features updated.');
    }

    public function show(Request $request, int $id): View
    {
        $auth = $this->authUser($request);
        $this->ensureSuperAdmin($auth->role_slug);

        $tenant = Tenant::query()->findOrFail($id);
        $subscriptions = TenantSubscription::query()
            ->where('tenant_id', $tenant->id)
            ->latest('id')
            ->limit(20)
            ->get();
        $users = User::query()
            ->where('tenant_id', $tenant->id)
            ->whereNull('deleted_at')
            ->orderBy('id', 'desc')
            ->limit(50)
            ->get(['id', 'name', 'email', 'role_slug', 'is_active']);
        $features = Feature::query()->orderBy('name')->get();
        $featureMap = TenantFeature::query()
            ->where('tenant_id', $tenant->id)
            ->get()
            ->keyBy('feature_id');

        return view('saas.tenant-show', compact('tenant', 'subscriptions', 'users', 'features', 'featureMap'));
    }

    public function approve(Request $request, int $id): RedirectResponse
    {
        $auth = $this->authUser($request);
        $this->ensureSuperAdmin($auth->role_slug);
        $tenant = Tenant::query()->findOrFail($id);

        $subdomain = $tenant->subdomain ?: $this->uniqueSubdomain((string) ($tenant->slug ?: $tenant->name), $tenant->id);
        $tenant->update([
            'is_active' => 1,
            'subscription_status' => 'active',
            'subdomain' => $subdomain,
            'access_url' => $this->tenantAccessUrl($request, $subdomain, (string) ($tenant->custom_domain ?? '')),
        ]);

        TenantSubscription::query()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'plan_type' => (string) $tenant->plan_type, 'starts_at' => $tenant->subscription_start_date],
            [
                'status' => 'active',
                'ends_at' => $tenant->subscription_end_date,
                'amount' => 0,
                'currency' => $tenant->currency ?: 'USD',
            ]
        );

        User::query()->where('tenant_id', $tenant->id)->whereIn('role_slug', ['admin', 'agent', 'sub_agent', 'student'])->update(['is_active' => 1]);

        $freshTenant = $tenant->fresh();
        $this->audit($request, 'saas.tenant_approve', 'tenant', $tenant->id, []);

        return back()->with('success', 'Tenant approved and account activated. Access URL: '.($freshTenant->access_url ?: $this->tenantAccessUrl($request, (string) $freshTenant->subdomain, (string) ($freshTenant->custom_domain ?? ''))));
    }

    private function uniqueSubdomain(string $seed, ?int $ignoreTenantId = null): string
    {
        $base = Str::slug($seed) ?: 'tenant';
        $base = mb_substr($base, 0, 56);
        $subdomain = $base;
        $suffix = 2;
        while (Tenant::query()
            ->where('subdomain', $subdomain)
            ->when($ignoreTenantId, fn ($query) => $query->where('id', '!=', $ignoreTenantId))
            ->exists()) {
            $subdomain = mb_substr($base, 0, 52).'-'.$suffix++;
        }

        return $subdomain;
    }

    private function tenantAccessUrl(Request $request, string $subdomain, string $customDomain = ''): string
    {
        $customDomain = preg_replace('#^https?://#', '', trim($customDomain)) ?: '';
        if ($customDomain !== '') {
            return 'https://'.$customDomain;
        }
        $baseDomain = trim((string) config('app.saas_base_domain', ''));
        if ($baseDomain === '') {
            $host = $request->getHost();
            $baseDomain = preg_replace('/^www\./', '', $host) ?: $host;
        }
        $scheme = $request->isSecure() ? 'https' : 'http';

        return $scheme.'://'.$subdomain.'.'.$baseDomain;
    }

    private function ensureSuperAdmin(string $role): void
    {
        if ($role !== 'super_admin') {
            abort(403, 'Only super admin can manage SaaS tenants.');
        }
    }
}
