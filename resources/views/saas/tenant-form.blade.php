@php($isEdit = !is_null($tenant))
<div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;">
    <div>
        <label>Company Name</label>
        <input name="name" required value="{{ old('name', $tenant->name ?? '') }}">
    </div>
    <div>
        <label>Owner Name</label>
        <input name="owner_name" value="{{ old('owner_name', $tenant->owner_name ?? '') }}">
    </div>
    <div>
        <label>Subdomain</label>
        <input name="subdomain" value="{{ old('subdomain', $tenant->subdomain ?? '') }}" placeholder="company-name">
    </div>
    <div>
        <label>Custom Domain</label>
        <input name="custom_domain" value="{{ old('custom_domain', $tenant->custom_domain ?? '') }}" placeholder="crm.company.com">
    </div>
    <div>
        <label>Email</label>
        <input type="email" name="email" value="{{ old('email', $tenant->email ?? '') }}">
    </div>
    <div>
        <label>Phone</label>
        <input name="phone" value="{{ old('phone', $tenant->phone ?? '') }}">
    </div>
    <div>
        <label>Currency</label>
        <input name="currency" value="{{ old('currency', $tenant->currency ?? 'USD') }}">
    </div>
    <div>
        <label>Plan Type</label>
        @php($plan = old('plan_type', $tenant->plan_type ?? 'monthly'))
        <select name="plan_type" required>
            <option value="monthly" {{ $plan === 'monthly' ? 'selected' : '' }}>Monthly</option>
            <option value="3_months" {{ $plan === '3_months' ? 'selected' : '' }}>3 Months</option>
            <option value="6_months" {{ $plan === '6_months' ? 'selected' : '' }}>6 Months</option>
            <option value="1_year" {{ $plan === '1_year' ? 'selected' : '' }}>1 Year</option>
        </select>
    </div>
    <div>
        <label>Subscription Start</label>
        <input type="date" name="subscription_start_date" required value="{{ old('subscription_start_date', isset($tenant->subscription_start_date) ? \Illuminate\Support\Carbon::parse($tenant->subscription_start_date)->format('Y-m-d') : now()->format('Y-m-d')) }}">
    </div>
    <div>
        <label>Subscription End</label>
        <input type="date" name="subscription_end_date" required value="{{ old('subscription_end_date', isset($tenant->subscription_end_date) ? \Illuminate\Support\Carbon::parse($tenant->subscription_end_date)->format('Y-m-d') : now()->addMonth()->format('Y-m-d')) }}">
    </div>
    <div>
        <label>Subscription Status</label>
        @php($subStatus = old('subscription_status', $tenant->subscription_status ?? 'active'))
        <select name="subscription_status" required>
            <option value="active" {{ $subStatus === 'active' ? 'selected' : '' }}>Active</option>
            <option value="expired" {{ $subStatus === 'expired' ? 'selected' : '' }}>Expired</option>
            <option value="suspended" {{ $subStatus === 'suspended' ? 'selected' : '' }}>Suspended</option>
        </select>
    </div>
    <div>
        <label>Login Access</label>
        @php($active = (string) old('is_active', isset($tenant) ? (string) $tenant->is_active : '1'))
        <select name="is_active" required>
            <option value="1" {{ $active === '1' ? 'selected' : '' }}>Enabled</option>
            <option value="0" {{ $active === '0' ? 'selected' : '' }}>Disabled</option>
        </select>
    </div>
</div>
