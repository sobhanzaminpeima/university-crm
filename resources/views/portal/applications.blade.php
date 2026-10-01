@extends('layouts.portal')

@section('content')
<div class="card">
    <h2 style="margin-top:0;">My Applications</h2>
    <p class="footer-note">Track each stage from draft to enrollment.</p>
    @php($stages = ['draft' => 'Draft', 'submitted' => 'Submitted', 'under_review' => 'Under Review', 'accepted' => 'Accepted', 'enrolled' => 'Enrolled'])
    <table>
        <thead><tr><th>Program</th><th>Status</th><th>Deadline</th><th>Progress</th><th>Timeline</th></tr></thead>
        <tbody>
        @forelse($applications as $a)
            @php
                $stageKeys = array_keys($stages);
                $idx = array_search($a->status, $stageKeys, true);
                $idx = $idx === false ? 0 : $idx;
                $p = (int) round((($idx + 1) / count($stageKeys)) * 100);
            @endphp
            <tr>
                <td>{{ $a->program }}</td>
                <td><span class="badge {{ $a->status }}">{{ ucfirst(str_replace('_', ' ', $a->status)) }}</span></td>
                <td>{{ $a->deadline ?: '-' }}</td>
                <td>
                    <div style="height:8px;background:#dbeafe;border-radius:999px;overflow:hidden;">
                        <div style="height:8px;width:{{ $p }}%;background:#0284c7;"></div>
                    </div>
                    <span class="footer-note">{{ $p }}%</span>
                </td>
                <td>
                    <div style="display:flex;gap:4px;flex-wrap:wrap;">
                        @foreach($stages as $key => $label)
                            @php($done = array_search($key, $stageKeys, true) <= $idx)
                            <span class="badge {{ $done ? 'enrolled' : 'lead' }}" style="font-size:11px;">{{ $label }}</span>
                        @endforeach
                    </div>
                </td>
            </tr>
        @empty
            <tr><td colspan="5">No applications yet. Submit a university request from Universities page.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@endsection
