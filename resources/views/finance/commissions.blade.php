@extends('layouts.app')

@section('content')
<div class="page-shell">
<div class="tabs">
    <a class="tab" href="/finance">Payments</a>
    <a class="tab active" href="/finance/commissions">Agent Commissions & Payouts</a>
</div>

<div class="card">
    <h3 style="margin-top:0;">Commission Balances</h3>
    <p class="footer-note">Commission earned is calculated from paid payments (commission_amount) for each agent's own students. Balance = Earned − Paid Out.</p>
    @if($rows->isEmpty())
        <p class="footer-note">No commission activity yet. Commission is calculated once a payment with a commission rate is marked "paid".</p>
    @else
        <table class="table-compact">
            <thead><tr><th>Agent</th><th>Role</th><th>Currency</th><th>Earned</th><th>Paid Out</th><th>Balance Due</th><th>Action</th></tr></thead>
            <tbody>
            @foreach($rows as $row)
                <tr>
                    <td>{{ $row['agent']->name }}</td>
                    <td>{{ ucfirst(str_replace('_',' ', $row['agent']->role_slug)) }}</td>
                    <td>{{ $row['currency'] }}</td>
                    <td>{{ number_format($row['earned'], 2) }}</td>
                    <td>{{ number_format($row['paid_out'], 2) }}</td>
                    <td><strong class="{{ $row['balance'] > 0 ? 'hot' : '' }}">{{ number_format($row['balance'], 2) }}</strong></td>
                    <td>
                        @if($row['balance'] > 0)
                            <button class="secondary" onclick="openPayoutModal({{ $row['agent']->id }}, '{{ $row['currency'] }}', {{ $row['balance'] }})">Record Payout</button>
                        @else
                            <span class="footer-note">Settled</span>
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endif
</div>

<div class="card" style="margin-top:12px;">
    <h3 style="margin-top:0;">Payout History</h3>
    @if($payoutHistory->isEmpty())
        <p class="footer-note">No payouts recorded yet.</p>
    @else
        <table class="table-compact">
            <thead><tr><th>Date</th><th>Agent</th><th>Currency</th><th>Amount</th><th>Note</th></tr></thead>
            <tbody>
            @foreach($payoutHistory as $payout)
                <tr>
                    <td>{{ $payout->paid_at ? \Illuminate\Support\Carbon::parse($payout->paid_at)->format('Y-m-d') : '-' }}</td>
                    <td>{{ $payout->agent?->name ?: ('#'.$payout->agent_id) }}</td>
                    <td>{{ $payout->currency }}</td>
                    <td>{{ number_format((float) $payout->amount, 2) }}</td>
                    <td>{{ $payout->note ?: '-' }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endif
</div>

<dialog id="payoutModal" class="card">
    <h3 style="margin-top:0;">Record Commission Payout</h3>
    <form method="POST" action="/finance/commissions">
        @csrf
        <input type="hidden" name="agent_id" id="payoutAgentId">
        <div class="grid-4">
            <input type="text" id="payoutAgentLabel" disabled>
            <input type="text" name="currency" id="payoutCurrency" readonly>
            <input type="number" name="amount" id="payoutAmount" step="0.01" min="0.01" required>
            <input type="text" name="note" placeholder="Note (optional)">
        </div>
        <div style="margin-top:10px;display:flex;gap:8px;">
            <button type="submit">Save Payout</button>
            <button type="button" class="secondary" onclick="document.getElementById('payoutModal').close()">Cancel</button>
        </div>
    </form>
</dialog>

<script>
function openPayoutModal(agentId, currency, balance) {
    document.getElementById('payoutAgentId').value = agentId;
    document.getElementById('payoutCurrency').value = currency;
    document.getElementById('payoutAmount').value = balance;
    document.getElementById('payoutAgentLabel').value = 'Agent #' + agentId;
    document.getElementById('payoutModal').showModal();
}
</script>
</div>
@endsection
