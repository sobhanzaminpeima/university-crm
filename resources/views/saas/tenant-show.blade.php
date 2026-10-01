@extends('layouts.app')

@section('content')
<div class="toolbar" style="margin-bottom:12px;">
    <a href="/saas/tenants" class="secondary" style="padding:9px 12px;border-radius:10px;text-decoration:none;">&larr; Back to Customers</a>
</div>

<div class="grid-4">
    <div class="card"><h3>Company</h3><div class="metric" style="font-size:20px;">{{ $tenant->name }}</div></div>
    <div class="card"><h3>Owner</h3><div class="metric" style="font-size:20px;">{{ $tenant->owner_name ?: '-' }}</div></div>
    <div class="card"><h3>Current Plan</h3><div class="metric" style="font-size:20px;">{{ strtoupper((string)$tenant->plan_type) }}</div></div>
    <div class="card"><h3>Status</h3><div class="metric" style="font-size:20px;">{{ $tenant->subscription_status }}</div></div>
</div>

<div class="two-col" style="margin-top:12px;">
    <div class="card">
        <h3>Tenant Profile</h3>
        <div class="stat-row"><span>Email</span><strong>{{ $tenant->email ?: '-' }}</strong></div>
        <div class="stat-row"><span>Phone</span><strong>{{ $tenant->phone ?: '-' }}</strong></div>
        <div class="stat-row"><span>Subdomain</span><strong>{{ $tenant->subdomain ?: '-' }}</strong></div>
        <div class="stat-row"><span>Custom Domain</span><strong>{{ $tenant->custom_domain ?: '-' }}</strong></div>
        <div class="stat-row"><span>Access URL</span><strong>@if($tenant->access_url)<a href="{{ $tenant->access_url }}" target="_blank" rel="noopener">{{ $tenant->access_url }}</a>@else - @endif</strong></div>
        <div class="stat-row"><span>Currency</span><strong>{{ $tenant->currency ?: '-' }}</strong></div>
        <div class="stat-row"><span>Subscription Window</span><strong>{{ $tenant->subscription_start_date }} &rarr; {{ $tenant->subscription_end_date }}</strong></div>
        <div class="stat-row"><span>Login Access</span><strong>{{ (int)$tenant->is_active === 1 ? 'Enabled' : 'Disabled' }}</strong></div>
    </div>
    <div class="card">
        <h3>Enabled Features</h3>
        @foreach($features as $feature)
            @php($enabled = (int)($featureMap[$feature->id]->is_enabled ?? 0) === 1)
            <div class="stat-row">
                <span>{{ $feature->name }}</span>
                <strong class="{{ $enabled ? 'accepted' : 'rejected' }} badge">{{ $enabled ? 'Enabled' : 'Disabled' }}</strong>
            </div>
        @endforeach
    </div>
</div>

<div class="two-col" style="margin-top:12px;">
    <div class="card">
        <h3>Recent Subscriptions</h3>
        <table class="table-compact">
            <thead><tr><th>Plan</th><th>Status</th><th>Amount</th><th>Window</th></tr></thead>
            <tbody>
            @forelse($subscriptions as $subscription)
                <tr>
                    <td>{{ $subscription->plan_type }}</td>
                    <td>{{ $subscription->status }}</td>
                    <td>{{ $subscription->currency }} {{ number_format((float)$subscription->amount,2) }}</td>
                    <td>{{ optional($subscription->starts_at)->format('Y-m-d') }} &rarr; {{ optional($subscription->ends_at)->format('Y-m-d') }}</td>
                </tr>
            @empty
                <tr><td colspan="4">No subscription rows yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="card">
        <h3>Users (Latest)</h3>
        <table class="table-compact">
            <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Active</th></tr></thead>
            <tbody>
            @forelse($users as $u)
                <tr>
                    <td>{{ $u->name }}</td>
                    <td>{{ $u->email }}</td>
                    <td>{{ $u->role_slug }}</td>
                    <td>{{ (int)$u->is_active === 1 ? 'Yes' : 'No' }}</td>
                </tr>
            @empty
                <tr><td colspan="4">No users found.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
