<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\SaasPackage;
use App\Models\Tenant;
use App\Models\TenantFeature;
use App\Models\TenantSubscription;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Illuminate\Http\JsonResponse;

class LandingController extends Controller
{
    public function index(): View
    {
        $plans = SaasPackage::query()
            ->where('is_active', 1)
            ->orderBy('sort_order')
            ->orderBy('duration_months')
            ->get();

        return view('landing.index', compact('plans'));
    }

    public function showRegister(Request $request): View
    {
        $plans = SaasPackage::query()->where('is_active', 1)->orderBy('sort_order')->get();
        $selectedPlanId = (int) $request->query('plan_id', 0);
        return view('landing.register', compact('plans', 'selectedPlanId'));
    }

    public function register(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'plan_id' => 'required|integer|exists:saas_packages,id',
            'company_name' => 'required|string|max:190',
            'full_name' => 'required|string|max:120',
            'email' => 'required|email|max:190|unique:users,email',
            'password' => 'required|string|min:8|max:120',
            'phone' => 'nullable|string|max:60',
        ]);

        $plan = SaasPackage::query()->where('is_active', 1)->findOrFail((int) $data['plan_id']);
        $now = Carbon::now();
        $end = $now->copy()->addMonths((int) $plan->duration_months);

        DB::transaction(function () use ($request, $data, $plan, $now, $end): void {
            $subdomain = $this->uniqueSubdomain((string) $data['company_name']);
            $tenant = Tenant::query()->create([
                'name' => trim((string) $data['company_name']),
                'slug' => Str::slug((string) $data['company_name']).'-'.Str::lower(Str::random(5)),
                'subdomain' => $subdomain,
                'access_url' => $this->tenantAccessUrl($request, $subdomain),
                'owner_name' => trim((string) $data['full_name']),
                'email' => mb_strtolower(trim((string) $data['email'])),
                'phone' => trim((string) ($data['phone'] ?? '')),
                'currency' => (string) $plan->currency,
                'is_active' => 0,
                'plan_type' => $this->planTypeFromMonths((int) $plan->duration_months),
                'billing_cycle' => $this->planTypeFromMonths((int) $plan->duration_months),
                'subscription_start_date' => $now->toDateString(),
                'subscription_end_date' => $end->toDateString(),
                'subscription_status' => 'suspended',
            ]);

            $adminRole = Role::query()->where('slug', 'admin')->first();
            $roleSlug = $adminRole ? 'admin' : 'super_admin';

            User::query()->create([
                'tenant_id' => $tenant->id,
                'name' => trim((string) $data['full_name']),
                'email' => mb_strtolower(trim((string) $data['email'])),
                'password' => Hash::make((string) $data['password']),
                'role_slug' => $roleSlug,
                'language' => 'en',
                'is_active' => 0,
            ]);

            TenantSubscription::query()->create([
                'tenant_id' => $tenant->id,
                'saas_package_id' => $plan->id,
                'plan_type' => $this->planTypeFromMonths((int) $plan->duration_months),
                'status' => 'suspended',
                'starts_at' => $now,
                'ends_at' => $end,
                'amount' => $plan->price,
                'currency' => (string) $plan->currency,
            ]);

            $featureStates = DB::table('features')->pluck('id')->all();
            foreach ($featureStates as $featureId) {
                TenantFeature::query()->updateOrCreate(
                    ['tenant_id' => $tenant->id, 'feature_id' => (int) $featureId],
                    ['is_enabled' => 1]
                );
            }
        });

        return redirect('/login')->with('success', 'Registration submitted. Your account will be activated by admin.');
    }

    public function privacy(): View
    {
        return view('landing.privacy');
    }

    public function contact(): View
    {
        return view('landing.contact');
    }

    public function plansApi(): JsonResponse
    {
        $plans = SaasPackage::query()
            ->where('is_active', 1)
            ->orderBy('sort_order')
            ->orderBy('duration_months')
            ->get(['id', 'name', 'slug', 'price', 'currency', 'duration_months', 'features_json']);

        return response()->json(['data' => $plans]);
    }

    private function planTypeFromMonths(int $months): string
    {
        return match ($months) {
            3 => '3_months',
            6 => '6_months',
            12 => '1_year',
            default => 'monthly',
        };
    }

    private function uniqueSubdomain(string $seed): string
    {
        $base = Str::slug($seed) ?: 'tenant';
        $base = mb_substr($base, 0, 56);
        $subdomain = $base;
        $suffix = 2;
        while (Tenant::query()->where('subdomain', $subdomain)->exists()) {
            $subdomain = mb_substr($base, 0, 52).'-'.$suffix++;
        }

        return $subdomain;
    }

    private function tenantAccessUrl(Request $request, string $subdomain): string
    {
        $baseDomain = trim((string) config('app.saas_base_domain', ''));
        if ($baseDomain === '') {
            $host = $request->getHost();
            $baseDomain = preg_replace('/^www\./', '', $host) ?: $host;
        }
        $scheme = $request->isSecure() ? 'https' : 'http';

        return $scheme.'://'.$subdomain.'.'.$baseDomain;
    }
}
