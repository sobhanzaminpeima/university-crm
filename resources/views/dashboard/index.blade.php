@extends('layouts.app')

@section('content')
<div class="page-shell">
<div class="hero monitor-hero">
    <h2>Control Center</h2>
    <div class="footer-note" style="color:#dbeafe;">Track requests, pipeline and critical actions in one screen.</div>
</div>

<div class="grid-4">
    <div class="card"><h3>Total Students</h3><div class="metric">{{ $stats['students'] }}</div></div>
    <div class="card"><h3>Active Applications</h3><div class="metric">{{ $stats['active_applications'] }}</div></div>
    <div class="card"><h3>Pending Tasks</h3><div class="metric">{{ $stats['pending_tasks'] }}</div></div>
    <div class="card"><h3>Unread Alerts</h3><div class="metric">{{ $unreadNotifications }}</div></div>
    <div class="card"><h3>Overdue Tasks</h3><div class="metric">{{ $overdueTasks }}</div></div>
    @if($canViewConversions)
    <div class="card"><h3>Conversion Rate</h3><div class="metric">{{ number_format((float)$conversionRate, 2) }}%</div></div>
    <div class="card"><h3>Visa Success Rate</h3><div class="metric">{{ number_format((float)$visaSuccessRate, 2) }}%</div></div>
    @endif
    <div class="card"><h3>Overall CAC</h3><div class="metric">{{ $overallCac !== null ? number_format((float)$overallCac, 2) : '-' }}</div></div>
    <div class="card"><h3>Revenue Forecast (Next Month)</h3><div class="metric">{{ number_format((float)$revenueForecastNextMonth, 2) }}</div></div>
</div>

<div class="three-col" style="margin-top:12px;">
    <div class="card">
        <h3>Pipeline Snapshot</h3>
        <div class="stat-row"><span>Lead</span><strong>{{ $pipeline['lead'] }}</strong></div>
        <div class="stat-row"><span>Applied</span><strong>{{ $pipeline['applied'] }}</strong></div>
        <div class="stat-row"><span>Enrolled</span><strong>{{ $pipeline['enrolled'] }}</strong></div>
        <div style="margin-top:10px;"><a class="tab" href="/pipeline">Open Pipeline Board</a></div>
    </div>

    <div class="card">
        <h3>Top Programs</h3>
        @forelse($topPrograms as $program)
            <div class="stat-row"><span>{{ $program->program }}</span><strong>{{ $program->total }}</strong></div>
        @empty
            <p class="footer-note">No program data yet.</p>
        @endforelse
    </div>

    <div class="card">
        <h3>Quick Actions</h3>
        <p><a href="/student-requests">Review Student Requests</a></p>
        <p><a href="/tasks">Manage Tasks</a></p>
        <p><a href="/messages">Open CRM Messages</a></p>
        <p><a href="/students">Students Registry</a></p>
        <p><a href="/applications">Applications Desk</a></p>
        <p><a href="/finance">Finance</a></p>
        <p><a href="/scholarships">Scholarships</a></p>
        @if($canViewConversions)
        <p><a href="/agents/performance">Agent Performance</a></p>
        @endif
    </div>
</div>

<div class="card" style="margin-top:12px;">
    <h3>Revenue (Last 6 Months)</h3>
    @if($monthlyRevenue->isEmpty())
        <p class="footer-note">No paid transactions yet.</p>
    @else
        @php $maxRevenue = max(1, (float) $monthlyRevenue->max('total')); @endphp
        @foreach($monthlyRevenue as $row)
            @php $w = (int) round(((float) $row->total / $maxRevenue) * 100); @endphp
            <div style="margin-bottom:8px;">
                <div class="stat-row"><span>{{ $row->ym }}</span><strong>{{ number_format((float) $row->total, 2) }}</strong></div>
                <div style="height:8px;background:#e2e8f0;border-radius:999px;overflow:hidden;">
                    <div style="height:8px;width:{{ $w }}%;background:#0ea5e9;"></div>
                </div>
            </div>
        @endforeach
    @endif
</div>

<div class="two-col" style="margin-top:12px;">
    <div class="card">
        <h3>Recent Students</h3>
        <table class="table-compact">
            <thead>
            <tr>
                <th>Name</th><th>Nationality</th><th>Stage</th><th>Heat</th><th>Action</th>
            </tr>
            </thead>
            <tbody>
            @foreach($recentStudents as $student)
                <tr>
                    <td>{{ $student->full_name }}</td>
                    <td>{{ $student->nationality ?: '-' }}</td>
                    <td><span class="badge {{ $student->stage }}">{{ ucfirst($student->stage) }}</span></td>
                    <td class="{{ $student->stage_temperature }}">{{ ucfirst($student->stage_temperature ?: 'cold') }}</td>
                    <td><a href="/students/{{ $student->id }}">More info</a></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>

    <div class="card">
        <h3>Latest Notifications</h3>
        @forelse($notifications as $n)
            <div class="notif-item {{ $n->read_at ? '' : 'unread' }}">
                <strong>{{ $n->title }}</strong>
                <div class="footer-note">{{ $n->body }}</div>
            </div>
        @empty
            <p class="footer-note">No notifications.</p>
        @endforelse
        <div style="margin-top:10px;">
            <a class="tab" href="/notifications">Open Notification Center</a>
        </div>
    </div>
</div>

@if($canViewConversions)
<div class="card" style="margin-top:12px;">
    <h3>Source ROI & CAC</h3>
    <table class="table-compact">
        <thead><tr><th>Source</th><th>Leads</th><th>Enrolled</th><th>Cost</th><th>CAC</th><th>ROI %</th></tr></thead>
        <tbody>
        @forelse($sourceRoi as $row)
            <tr>
                <td>{{ ucwords(str_replace('_', ' ', $row->source_key)) }}</td>
                <td>{{ $row->leads }}</td>
                <td>{{ $row->enrolled }}</td>
                <td>{{ number_format((float)$row->cost, 2) }}</td>
                <td>{{ $row->cac !== null ? number_format((float)$row->cac, 2) : '-' }}</td>
                <td>{{ $row->roi !== null ? number_format((float)$row->roi, 2).'%' : '-' }}</td>
            </tr>
        @empty
            <tr><td colspan="6">No source data yet.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@endif

@if($canViewConversions)
<div class="card" style="margin-top:12px;">
    <h3>Upcoming Tasks</h3>
    <table class="table-compact">
        <thead><tr><th>Title</th><th>Assignee</th><th>Status</th><th>Deadline</th></tr></thead>
        <tbody>
            @forelse($upcomingTasks as $task)
                <tr>
                    <td>{{ $task->title }}</td>
                    <td>#{{ $task->assigned_to }}</td>
                    <td><span class="badge {{ $task->status }}">{{ ucfirst(str_replace('_', ' ', $task->status)) }}</span></td>
                    <td>{{ $task->deadline ? \Illuminate\Support\Carbon::parse($task->deadline)->format('Y-m-d H:i') : '-' }}</td>
                </tr>
            @empty
                <tr><td colspan="4">No pending tasks.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="card" style="margin-top:12px;">
    <h3>Funnel Conversion</h3>
    <div class="grid-4">
        <div class="card">
            <div class="stat-row"><span>Lead</span><strong>{{ $pipeline['lead'] }}</strong></div>
            <div style="height:8px;background:#e2e8f0;border-radius:999px;overflow:hidden;"><div style="height:8px;width:{{ $funnelPercentages['lead'] }}%;background:#60a5fa;"></div></div>
            <p class="footer-note">{{ $funnelPercentages['lead'] }}%</p>
        </div>
        <div class="card">
            <div class="stat-row"><span>Applied</span><strong>{{ $pipeline['applied'] }}</strong></div>
            <div style="height:8px;background:#e2e8f0;border-radius:999px;overflow:hidden;"><div style="height:8px;width:{{ $funnelPercentages['applied'] }}%;background:#f59e0b;"></div></div>
            <p class="footer-note">{{ $funnelPercentages['applied'] }}%</p>
        </div>
        <div class="card">
            <div class="stat-row"><span>Enrolled</span><strong>{{ $pipeline['enrolled'] }}</strong></div>
            <div style="height:8px;background:#e2e8f0;border-radius:999px;overflow:hidden;"><div style="height:8px;width:{{ $funnelPercentages['enrolled'] }}%;background:#10b981;"></div></div>
            <p class="footer-note">{{ $funnelPercentages['enrolled'] }}%</p>
        </div>
    </div>
</div>
@endif
 </div>
@endsection
