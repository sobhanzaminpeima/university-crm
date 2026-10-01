@extends('layouts.app')

@section('content')
<div class="page-shell">
<div class="tabs">
    <a class="tab active" href="/finance">Payments</a>
    <a class="tab" href="/finance/commissions">Agent Commissions & Payouts</a>
</div>
<div class="grid-4">
    <div class="card"><h3>Total Amount</h3><div class="metric">{{ number_format($summary['total_amount'], 2) }}</div></div>
    <div class="card"><h3>Paid Amount</h3><div class="metric">{{ number_format($summary['paid_amount'], 2) }}</div></div>
    <div class="card"><h3>Commission</h3><div class="metric">{{ number_format($summary['commission'], 2) }}</div></div>
    <div class="card"><h3>Outstanding</h3><div class="metric">{{ number_format(max(0, $summary['total_amount'] - $summary['paid_amount']), 2) }}</div></div>
</div>

@if($saasSales)
<div class="card" style="margin-top:12px;">
    <h3>SaaS Sales</h3>
    <div class="grid-4">
        <div class="card"><h3>Total SaaS Sales</h3><div class="metric">{{ number_format($saasSales['total'], 2) }}</div></div>
        <div class="card"><h3>Active MRR/ARR Pool</h3><div class="metric">{{ number_format($saasSales['active'], 2) }}</div></div>
        <div class="card"><h3>Active Subscriptions</h3><div class="metric">{{ $saasSales['active_count'] }}</div></div>
        <div class="card"><h3>Expired Subscriptions</h3><div class="metric">{{ $saasSales['expired_count'] }}</div></div>
    </div>
    <table class="table-compact" style="margin-top:10px;">
        <thead><tr><th>Tenant</th><th>Plan</th><th>Status</th><th>Amount</th><th>Start</th><th>End</th></tr></thead>
        <tbody>
        @forelse($recentSaasSubscriptions as $sub)
            <tr>
                <td>{{ $sub->tenant?->name ?: ('#'.$sub->tenant_id) }}</td>
                <td>{{ strtoupper((string)$sub->plan_type) }}</td>
                <td><span class="badge {{ $sub->status }}">{{ ucfirst($sub->status) }}</span></td>
                <td>{{ $sub->currency }} {{ number_format((float)$sub->amount, 2) }}</td>
                <td>{{ $sub->starts_at }}</td>
                <td>{{ $sub->ends_at }}</td>
            </tr>
        @empty
            <tr><td colspan="6">No SaaS subscription sales yet.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@endif

<div class="card" style="margin-top:12px;">
    <div class="toolbar">
        <form method="GET" action="/finance" style="display:flex;gap:8px;">
            <select name="status">
                <option value="">All statuses</option>
                @foreach(['pending','paid','failed','refunded'] as $s)
                    <option value="{{ $s }}" {{ $status === $s ? 'selected' : '' }}>{{ ucfirst($s) }}</option>
                @endforeach
            </select>
            <select name="currency">
                <option value="">All currencies</option>
                @foreach(['USD','EUR','GBP','TRY'] as $cur)
                    <option value="{{ $cur }}" {{ $currency === $cur ? 'selected' : '' }}>{{ $cur }}</option>
                @endforeach
            </select>
            <select name="per_page" class="per-page-select" title="Items per page" aria-label="Items per page" onchange="this.form.submit()">
                @foreach([15, 50, 100] as $size)
                    <option value="{{ $size }}" {{ (int)($perPage ?? 50) === $size ? 'selected' : '' }}>{{ $size }}</option>
                @endforeach
            </select>
            <button type="submit">Filter</button>
        </form>
        <button onclick="document.getElementById('addPayment').showModal()">+ Add Payment</button>
    </div>
    <table class="table-compact">
        <thead><tr><th>ID</th><th>Student</th><th>Type</th><th>Amount</th><th>Commission</th><th>Status</th><th>Paid At</th><th>Action</th></tr></thead>
        <tbody>
        @forelse($payments as $payment)
            <tr>
                <td>#{{ $payment->id }}</td>
                <td>{{ optional($students->firstWhere('id', $payment->student_id))->full_name ?: '#'.$payment->student_id }}</td>
                <td>{{ $payment->type }}</td>
                <td>{{ $payment->currency }} {{ number_format((float) $payment->amount, 2) }}</td>
                <td>{{ number_format((float) $payment->commission_amount, 2) }}</td>
                <td><span class="badge {{ $payment->status }}">{{ ucfirst($payment->status) }}</span></td>
                <td>{{ $payment->paid_at ?: '-' }}</td>
                <td style="display:flex;gap:6px;">
                    <button type="button" class="secondary" onclick="document.getElementById('editPayment{{ $payment->id }}').showModal()">Edit</button>
                    <form method="POST" action="/finance/{{ $payment->id }}" onsubmit="return confirm('Delete payment?')">
                        @csrf
                        @method('DELETE')
                        <button class="secondary" type="submit">Delete</button>
                    </form>
                </td>
            </tr>
        @empty
            <tr><td colspan="8">No payments found.</td></tr>
        @endforelse
        </tbody>
    </table>
    <div class="pagination-wrap">{{ $payments->links() }}</div>
</div>

<dialog id="addPayment" class="card" style="max-width:900px;">
    <h3 style="margin-top:0;">Add Payment</h3>
    <form method="POST" action="/finance">
        @csrf
        @include('finance.partials.form', ['payment' => null, 'students' => $students])
        <div style="margin-top:10px;display:flex;gap:8px;">
            <button type="submit" onclick="return confirm('Save this payment?')">Save</button>
            <button type="button" class="secondary" onclick="document.getElementById('addPayment').close()">Cancel</button>
        </div>
    </form>
</dialog>

@foreach($payments as $payment)
<dialog id="editPayment{{ $payment->id }}" class="card" style="max-width:900px;">
    <h3 style="margin-top:0;">Edit Payment #{{ $payment->id }}</h3>
    <form method="POST" action="/finance/{{ $payment->id }}">
        @csrf
        @method('PUT')
        @include('finance.partials.form', ['payment' => $payment, 'students' => $students])
        <div style="margin-top:10px;display:flex;gap:8px;">
            <button type="submit" onclick="return confirm('Update this payment?')">Update</button>
            <button type="button" class="secondary" onclick="document.getElementById('editPayment{{ $payment->id }}').close()">Cancel</button>
        </div>
    </form>
</dialog>
@endforeach
 </div>
@endsection
