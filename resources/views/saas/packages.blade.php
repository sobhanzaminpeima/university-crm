@extends('layouts.app')

@section('content')
<div class="card">
    <div class="toolbar">
        <h3 style="margin:0;">SaaS Package Management</h3>
        <button onclick="document.getElementById('addPackage').showModal()">+ New Package</button>
    </div>
    <table>
        <thead><tr><th>Name</th><th>Price</th><th>Duration</th><th>Status</th><th>Features</th><th>Actions</th></tr></thead>
        <tbody>
        @forelse($packages as $package)
            <tr>
                <td>{{ $package->name }}</td>
                <td>{{ $package->currency }} {{ number_format((float) $package->price, 2) }}</td>
                <td>{{ $package->duration_months }} month(s)</td>
                <td>{{ (int)$package->is_active === 1 ? 'Active' : 'Inactive' }}</td>
                <td>{{ implode(', ', $package->features_json ?? []) }}</td>
                <td style="display:flex;gap:8px;flex-wrap:wrap;">
                    <button type="button" class="secondary" onclick="document.getElementById('editPkg{{ $package->id }}').showModal()">Edit</button>
                    <form method="POST" action="/saas/packages/{{ $package->id }}" onsubmit="return confirm('Delete package?')">
                        @csrf @method('DELETE')
                        <button type="submit" class="secondary">Delete</button>
                    </form>
                </td>
            </tr>
        @empty
            <tr><td colspan="6">No packages found.</td></tr>
        @endforelse
        </tbody>
    </table>
    <div style="margin-top:10px;">{{ $packages->links() }}</div>
</div>

<dialog id="addPackage" class="card" style="max-width:760px;">
    <h3 style="margin-top:0;">New Package</h3>
    <form method="POST" action="/saas/packages">
        @csrf
        @include('saas.package-form', ['package' => null])
        <div style="display:flex;gap:8px;">
            <button type="submit">Create Package</button>
            <button type="button" class="secondary" onclick="document.getElementById('addPackage').close()">Cancel</button>
        </div>
    </form>
</dialog>

@foreach($packages as $package)
<dialog id="editPkg{{ $package->id }}" class="card" style="max-width:760px;">
    <h3 style="margin-top:0;">Edit Package</h3>
    <form method="POST" action="/saas/packages/{{ $package->id }}">
        @csrf @method('PUT')
        @include('saas.package-form', ['package' => $package])
        <div style="display:flex;gap:8px;">
            <button type="submit">Save Changes</button>
            <button type="button" class="secondary" onclick="document.getElementById('editPkg{{ $package->id }}').close()">Cancel</button>
        </div>
    </form>
</dialog>
@endforeach
@endsection

