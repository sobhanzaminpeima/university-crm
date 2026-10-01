@extends('layouts.app')

@section('content')
<div class="page-shell">
<div class="kpi-strip">
    <div class="kpi-tile"><div class="kpi-label">Overdue</div><div class="kpi-value hot">{{ $overdueCount }}</div></div>
    <div class="kpi-tile"><div class="kpi-label">Due in 14 Days</div><div class="kpi-value warm">{{ $dueSoonCount }}</div></div>
    <div class="kpi-tile"><div class="kpi-label">Total Tracked</div><div class="kpi-value">{{ $totalCount }}</div></div>
</div>

<div class="card" style="margin-top:12px;">
    <div class="tabs">
        <a class="tab {{ $range === 'upcoming' ? 'active' : '' }}" href="/calendar?range=upcoming">Upcoming</a>
        <a class="tab {{ $range === 'overdue' ? 'active' : '' }}" href="/calendar?range=overdue">Overdue</a>
        <a class="tab {{ $range === 'all' ? 'active' : '' }}" href="/calendar?range=all">All</a>
    </div>

    @forelse($grouped as $month => $items)
        <h3 style="margin-top:16px;">{{ \Illuminate\Support\Carbon::createFromFormat('Y-m', $month)->format('F Y') }}</h3>
        <table class="table-compact">
            <thead><tr><th>Deadline</th><th>Student</th><th>University</th><th>Program</th><th>Status</th><th>Urgency</th></tr></thead>
            <tbody>
            @foreach($items as $app)
                <tr>
                    <td>{{ $app->deadline }}</td>
                    <td><a href="/students/{{ $app->student_id }}">{{ $app->student_name ?: ('#'.$app->student_id) }}</a></td>
                    <td>{{ $app->university_name ?: ('#'.$app->university_id) }}</td>
                    <td><a href="/applications/{{ $app->id }}">{{ $app->program }}</a></td>
                    <td><span class="badge {{ $app->status }}">{{ ucwords(str_replace('_',' ', $app->status)) }}</span></td>
                    <td>
                        @if($app->urgency === 'overdue')
                            <span class="badge rejected">Overdue by {{ abs($app->days_left) }}d</span>
                        @elseif($app->urgency === 'critical')
                            <span class="badge applied">{{ $app->days_left }}d left</span>
                        @elseif($app->urgency === 'soon')
                            <span class="badge lead">{{ $app->days_left }}d left</span>
                        @else
                            <span class="footer-note">{{ $app->days_left }}d left</span>
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @empty
        <p class="footer-note" style="margin-top:12px;">No applications with deadlines in this view.</p>
    @endforelse
</div>
</div>
@endsection
