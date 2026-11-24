@extends('layouts.app')

@section('content')
<div class="card">
    <h2 style="margin-top:0;">{{ $user->name }}</h2>
    <p><strong>Email:</strong> {{ $user->email }}</p>
    <p><strong>Role:</strong> {{ $user->role_slug }}</p>
    <p><strong>Language:</strong> {{ $user->language }}</p>
    <p><strong>Status:</strong> {{ $user->is_active ? 'Active' : 'Inactive' }}</p>
</div>

@if(isset($students) && in_array($user->role_slug, ['agent','sub_agent'], true))
<div class="card" style="margin-top:12px;">
    <h3>{{ $user->role_slug === 'agent' ? 'Students from this Agent' : 'Students from this Sub-Agent' }}</h3>
    <table class="table-compact">
        <thead><tr><th>Name</th><th>Email</th><th>Stage</th><th>Action</th></tr></thead>
        <tbody>
        @forelse($students as $student)
            <tr>
                <td>{{ $student->full_name }}</td>
                <td>{{ $student->email }}</td>
                <td><span class="badge {{ $student->stage }}">{{ ucfirst(str_replace('_', ' ', $student->stage)) }}</span></td>
                <td><a class="tab" href="/students/{{ $student->id }}">Open</a></td>
            </tr>
        @empty
            <tr><td colspan="4">No students assigned yet.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@endif
@endsection
