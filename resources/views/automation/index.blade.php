@extends('layouts.app')

@section('content')
<h1>Automation Rules</h1>
<div class="card" style="margin-bottom:12px;">
    <h3 style="margin-top:0;">How to use</h3>
    <ul style="margin:0;padding-left:18px;line-height:1.8;">
        <li>Create a rule and select its trigger.</li>
        <li><strong>SLA Overdue Tasks:</strong> checks overdue tasks and flags them for follow-up.</li>
        <li><strong>Daily Follow-up:</strong> sends daily reminders for pending student/application actions.</li>
        <li><strong>Documents Pending 3 Days:</strong> creates auto follow-up task + notification + WhatsApp alert.</li>
        <li>Use <strong>Run Automations Now</strong> after changes to test behavior immediately.</li>
    </ul>
</div>
<div class="card">
    <form method="POST" action="/automation-rules">
        @csrf
        <input name="name" placeholder="Rule name" required>
        <select name="trigger_key" required>
            <option value="sla_overdue_tasks">SLA Overdue Tasks</option>
            <option value="daily_followup">Daily Follow-up</option>
            <option value="documents_pending_3days">Documents Pending 3 Days</option>
        </select>
        <button type="submit">Create Rule</button>
    </form>
    <form method="POST" action="/automation-rules/run" style="margin-top:10px;">
        @csrf
        <button type="submit">Run Automations Now</button>
    </form>
</div>

<div class="card" style="margin-top:12px;">
    <table>
        <thead><tr><th>Name</th><th>Trigger</th><th>Status</th></tr></thead>
        <tbody>
        @foreach($rules as $r)
            <tr>
                <td>{{ $r->name }}</td>
                <td>{{ $r->trigger_key }}</td>
                <td>{{ (int) $r->is_active === 1 ? 'Active' : 'Inactive' }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
@endsection

