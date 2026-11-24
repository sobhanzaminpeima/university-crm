@extends('layouts.app')

@section('content')
<div class="page-shell">
<div class="card">
    <div class="toolbar">
        <form id="searchForm" method="GET" action="/students" class="toolbar grow">
            <input id="student-search" type="search" name="q" aria-label="Search students" placeholder="Search name, email, phone, field..." value="{{ $q }}">
            <select name="stage" aria-label="Filter by stage">
                <option value="">All stages</option>
                @foreach(['lead','inquiry','applicant','documents_pending','interview_scheduled','admitted','visa_process','tuition_paid','enrolled','alumni'] as $st)
                    <option value="{{ $st }}" {{ $stage === $st ? 'selected' : '' }}>{{ ucfirst(str_replace('_', ' ', $st)) }}</option>
                @endforeach
            </select>
            <input name="country" aria-label="Target country" placeholder="Target country" value="{{ $country }}">
            <input name="gpa_min" aria-label="Minimum GPA" type="number" step="0.01" min="0" max="4" placeholder="GPA min" value="{{ $gpaMin }}">
            <input name="gpa_max" aria-label="Maximum GPA" type="number" step="0.01" min="0" max="4" placeholder="GPA max" value="{{ $gpaMax }}">
            <select name="per_page" class="per-page-select" title="Items per page" aria-label="Items per page" onchange="this.form.submit()">
                @foreach([15, 50, 100] as $size)
                    <option value="{{ $size }}" {{ (int)($perPage ?? 50) === $size ? 'selected' : '' }}>{{ $size }}</option>
                @endforeach
            </select>
            <button type="submit">Search</button>
            @if($q || $stage || $country || $gpaMin || $gpaMax)
                <a class="tab" href="/students">Clear filters</a>
            @endif
        </form>
        <button onclick="document.getElementById('addStudent').showModal()">+ Add Student</button>
    </div>
    @php $exportQuery = http_build_query(request()->except(['page', 'per_page'])); @endphp
    @include('partials.export-buttons', [
        'xlsx' => '/students/export?'.$exportQuery,
        'csv' => '/students/export?'.http_build_query(array_merge(request()->except(['page', 'per_page']), ['format' => 'csv'])),
        'note' => 'Exports every student matching the current search and filters (not only this page).',
    ])

    <table class="table-compact">
        <thead>
        <tr>
            <th>Student</th><th>Nationality</th><th>GPA</th><th>Field of Study</th><th>Lead Source</th><th>Uni Lang</th><th>Agent</th><th>Sub-Agent</th><th>Stage</th><th>Actions</th>
        </tr>
        </thead>
        <tbody>
        @foreach($students as $student)
            <tr>
                <td><a href="/students/{{ $student->id }}">{{ $student->full_name }}</a><div class="footer-note">{{ $student->email }}</div></td>
                <td>{{ $student->nationality ?: '-' }}</td>
                <td>{{ $student->gpa ?: '-' }}</td>
                <td>{{ $student->field_of_study ?: '-' }}</td>
                <td>{{ $student->lead_source ? ucwords(str_replace('_', ' ', $student->lead_source)) : '-' }}</td>
                <td>{{ strtoupper($student->preferred_university_language ?: '-') }}</td>
                <td>{{ $student->agent?->name ?: '-' }}</td>
                <td>{{ $student->subAgent?->name ?: '-' }}</td>
                <td><span class="badge {{ $student->stage }}">{{ ucfirst(str_replace('_', ' ', $student->stage)) }}</span></td>
                <td style="display:flex;gap:6px;">
                    <a class="tab" href="/students/{{ $student->id }}">View</a>
                    <button class="secondary" type="button" onclick="document.getElementById('editStudent{{ $student->id }}').showModal()">Edit</button>
                    <form method="POST" action="/students/{{ $student->id }}" onsubmit="return confirm(@js('Delete student "'.$student->full_name.'"? This also removes their portal access.'))">
                        @csrf @method('DELETE')
                        <button type="submit" class="secondary">Delete</button>
                    </form>
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
    <div class="pagination-wrap">{{ $students->links() }}</div>
</div>

@foreach($students as $student)
<dialog id="editStudent{{ $student->id }}" class="card form-dialog" aria-labelledby="editStudentTitle{{ $student->id }}">
    <h3 id="editStudentTitle{{ $student->id }}" style="margin-top:0;">Edit student: {{ $student->full_name }}</h3>
    <form method="POST" action="/students/{{ $student->id }}">
        @csrf
        @method('PUT')
        <input type="hidden" name="_modal" value="editStudent{{ $student->id }}">
        @include('students._form-fields', ['student' => $student, 'formId' => 'editStudent'.$student->id])
        <div style="margin-top:10px;display:flex;gap:8px;">
            <button type="submit">Save Changes</button>
            <button type="button" class="secondary" onclick="document.getElementById('editStudent{{ $student->id }}').close()">Cancel</button>
        </div>
    </form>
    <hr style="margin:12px 0;border:none;border-top:1px solid var(--border);">
    <form method="POST" action="/students/{{ $student->id }}/reset-password" class="toolbar">
        @csrf
        <label for="resetPw{{ $student->id }}" class="sr-only">New portal password</label>
        <input id="resetPw{{ $student->id }}" type="password" name="new_password" minlength="8" autocomplete="new-password" placeholder="Reset portal password (min 8)" required>
        <button type="submit" class="secondary">Reset Password</button>
    </form>
</dialog>
@endforeach

<dialog id="addStudent" class="card form-dialog" aria-labelledby="addStudentTitle">
    <h3 id="addStudentTitle" style="margin-top:0;">Add student</h3>
    @if(session('duplicate_warning'))
        <div class="card" style="border-color:#f59e0b;background:#fffbeb;margin-bottom:10px;">
            <strong>Possible duplicate student(s) found:</strong>
            <ul style="margin:6px 0 0 18px;">
                @foreach(session('duplicate_warning') as $dup)
                    <li><a href="/students/{{ $dup->id }}" target="_blank">{{ $dup->full_name }}</a> ({{ $dup->email }}{{ $dup->phone ? ' · '.$dup->phone : '' }})</li>
                @endforeach
            </ul>
        </div>
    @endif
    <form method="POST" action="/students">
        @csrf
        <input type="hidden" name="_modal" value="addStudent">
        @if(session('duplicate_warning'))
            <input type="hidden" name="confirm_duplicate" value="1">
        @endif
        @include('students._form-fields', ['student' => null, 'formId' => 'addStudent'])
        <div style="margin-top:10px;display:flex;gap:8px;">
            <button type="submit">Save student</button>
            <button type="button" class="secondary" onclick="document.getElementById('addStudent').close()">Cancel</button>
        </div>
    </form>
</dialog>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const modal = "{{ old('_modal') }}";
    // Re-open whichever student form was submitted so typed values and field errors are visible.
    if (/^(addStudent|editStudent\d+)$/.test(modal) && document.getElementById(modal)) {
        document.getElementById(modal).showModal();
    }
});
</script>
 </div>
@endsection
