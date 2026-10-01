@extends('layouts.app')

@section('content')
<div class="card">
    <div class="toolbar">
        <h3 style="margin:0;">SaaS Tenant Manager</h3>
        <button onclick="document.getElementById('addTenant').showModal()">+ New Tenant</button>
    </div>
    <table class="table-compact">
        <thead>
            <tr>
                <th>Company</th><th>Access</th><th>Owner</th><th>Plan</th><th>Subscription</th><th>Status</th><th>Actions</th>
            </tr>
        </thead>
        <tbody>
        @forelse($tenants as $tenant)
            <tr>
                <td>{{ $tenant->name }}<br><span class="footer-note">{{ $tenant->email }}</span></td>
                <td>
                    <strong>{{ $tenant->subdomain ?: '-' }}</strong>
                    <div class="footer-note">{{ $tenant->custom_domain ?: 'Subdomain' }}</div>
                    @if($tenant->access_url)
                        <a class="tab" href="{{ $tenant->access_url }}" target="_blank" rel="noopener">Open</a>
                    @endif
                </td>
                <td>{{ $tenant->owner_name ?: '-' }}<br><span class="footer-note">{{ $tenant->phone ?: '-' }}</span></td>
                <td>{{ strtoupper((string) $tenant->plan_type) }}</td>
                <td>{{ $tenant->subscription_start_date }} &rarr; {{ $tenant->subscription_end_date }}</td>
                <td>
                    <span class="badge {{ (string)$tenant->subscription_status === 'active' ? 'ok' : ((string)$tenant->subscription_status === 'suspended' ? 'warn' : 'cold') }}">
                        {{ $tenant->subscription_status }}
                    </span>
                    <div class="footer-note">Login: {{ (int)$tenant->is_active === 1 ? 'Enabled' : 'Disabled' }}</div>
                </td>
                <td style="display:flex;gap:8px;flex-wrap:wrap;">
                    <a href="/saas/tenants/{{ $tenant->id }}" class="secondary" style="padding:8px 10px;border-radius:10px;text-decoration:none;">View</a>
                    <button type="button" class="secondary" onclick="document.getElementById('editTenant{{ $tenant->id }}').showModal()">Edit</button>
                    <button type="button" class="secondary" onclick="document.getElementById('featuresTenant{{ $tenant->id }}').showModal()">Features</button>
                    @if((int)$tenant->is_active !== 1 || (string)$tenant->subscription_status !== 'active')
                        <form method="POST" action="/saas/tenants/{{ $tenant->id }}/approve">
                            @csrf
                            <button type="submit">Approve</button>
                        </form>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="7">No tenants found.</td></tr>
        @endforelse
        </tbody>
    </table>
    <div class="pagination-wrap">{{ $tenants->links() }}</div>
</div>

<dialog id="addTenant" class="card" style="max-width:780px;">
    <h3 style="margin-top:0;">Create Tenant</h3>
    <form method="POST" action="/saas/tenants">
        @csrf
        @include('saas.tenant-form', ['tenant' => null])
        <div style="display:flex;gap:8px;">
            <button type="submit">Create Tenant</button>
            <button type="button" class="secondary" onclick="document.getElementById('addTenant').close()">Cancel</button>
        </div>
    </form>
</dialog>

@foreach($tenants as $tenant)
<dialog id="editTenant{{ $tenant->id }}" class="card" style="max-width:780px;">
    <h3 style="margin-top:0;">Edit Tenant: {{ $tenant->name }}</h3>
    <form method="POST" action="/saas/tenants/{{ $tenant->id }}">
        @csrf
        @method('PUT')
        @include('saas.tenant-form', ['tenant' => $tenant])
        <div style="display:flex;gap:8px;">
            <button type="submit">Save Changes</button>
            <button type="button" class="secondary" onclick="document.getElementById('editTenant{{ $tenant->id }}').close()">Cancel</button>
        </div>
    </form>
</dialog>

<dialog id="featuresTenant{{ $tenant->id }}" class="card" style="max-width:760px;">
    <h3 style="margin-top:0;">Feature Toggles: {{ $tenant->name }}</h3>
    <form method="POST" action="/saas/tenants/{{ $tenant->id }}/features">
        @csrf
        <div class="tabs" style="display:flex;flex-wrap:wrap;gap:10px;">
            @foreach($features as $feature)
                @php($enabled = (int)($tenantFeatures[$tenant->id][$feature->id]->is_enabled ?? 0) === 1)
                <label class="tab">
                    <input type="checkbox" name="features[]" value="{{ $feature->id }}" {{ $enabled ? 'checked' : '' }}>
                    {{ $feature->name }}
                </label>
            @endforeach
        </div>
        <div style="display:flex;gap:8px;margin-top:10px;">
            <button type="submit">Save Features</button>
            <button type="button" class="secondary" onclick="document.getElementById('featuresTenant{{ $tenant->id }}').close()">Cancel</button>
        </div>
    </form>
</dialog>
@endforeach
@endsection
