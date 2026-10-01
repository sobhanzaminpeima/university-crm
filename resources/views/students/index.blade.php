@extends('layouts.app')

@section('content')
<div class="page-shell">
<div class="card">
    <div class="toolbar">
        <form id="searchForm" method="GET" action="/students" class="toolbar grow">
            <input id="global-search" type="text" name="q" placeholder="Search name, email, phone, field..." value="{{ $q }}">
            <select name="stage">
                <option value="">All stages</option>
                @foreach(['lead','inquiry','applicant','documents_pending','interview_scheduled','admitted','visa_process','tuition_paid','enrolled','alumni'] as $st)
                    <option value="{{ $st }}" {{ $stage === $st ? 'selected' : '' }}>{{ ucfirst($st) }}</option>
                @endforeach
            </select>
            <input name="country" placeholder="Target country" value="{{ $country }}">
            <input name="gpa_min" type="number" step="0.01" min="0" max="4" placeholder="GPA min" value="{{ $gpaMin }}">
            <input name="gpa_max" type="number" step="0.01" min="0" max="4" placeholder="GPA max" value="{{ $gpaMax }}">
            <select name="per_page" class="per-page-select" title="Items per page" aria-label="Items per page" onchange="this.form.submit()">
                @foreach([15, 50, 100] as $size)
                    <option value="{{ $size }}" {{ (int)($perPage ?? 50) === $size ? 'selected' : '' }}>{{ $size }}</option>
                @endforeach
            </select>
            <button type="submit">Search</button>
        </form>
        <button onclick="document.getElementById('addStudent').showModal()">+ Add Student</button>
    </div>

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
                <td><span class="badge {{ $student->stage }}">{{ ucfirst($student->stage) }}</span></td>
                <td style="display:flex;gap:6px;">
                    <a class="tab" href="/students/{{ $student->id }}">View</a>
                    <button class="secondary" type="button" onclick="document.getElementById('editStudent{{ $student->id }}').showModal()">Edit</button>
                    <form method="POST" action="/students/{{ $student->id }}" onsubmit="return confirm('Delete student?')">
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
<dialog id="editStudent{{ $student->id }}" class="card" style="max-width:820px;">
    <h3 style="margin-top:0;">Edit Student</h3>
    <form method="POST" action="/students/{{ $student->id }}">
        @csrf
        @method('PUT')
        <div class="grid-4">
            <input name="full_name" value="{{ $student->full_name }}" required>
            <input name="email" value="{{ $student->email }}" required>
            <input name="account_password" type="password" placeholder="Leave blank to keep password">
            <input name="phone" value="{{ $student->phone }}">
            <input name="nationality" value="{{ $student->nationality }}">
            <input name="gpa" value="{{ $student->gpa }}">
            <select name="field_of_study">
                <option value="">Field of Study</option>
                @foreach(($studyFields ?? collect()) as $field)
                    <option value="{{ $field }}" {{ (string)$student->field_of_study === (string)$field ? 'selected' : '' }}>{{ $field }}</option>
                @endforeach
            </select>
            <select name="preferred_university_language">
                <option value="">University language</option>
                <option value="en" {{ ($student->preferred_university_language ?? '') === 'en' ? 'selected' : '' }}>English</option>
                <option value="tr" {{ ($student->preferred_university_language ?? '') === 'tr' ? 'selected' : '' }}>Turkish</option>
            </select>
            <input name="english_level" value="{{ $student->english_level }}">
            <select name="stage">
                @foreach(['lead','inquiry','applicant','documents_pending','interview_scheduled','admitted','visa_process','tuition_paid','enrolled','alumni'] as $st)
                    <option value="{{ $st }}" {{ $student->stage === $st ? 'selected' : '' }}>{{ ucfirst($st) }}</option>
                @endforeach
            </select>
            <select name="lifecycle_stage">
                @foreach(['lead','inquiry','applicant','admitted','enrolled','alumni'] as $lc)
                    <option value="{{ $lc }}" {{ ($student->lifecycle_stage ?? '') === $lc ? 'selected' : '' }}>{{ ucfirst($lc) }}</option>
                @endforeach
            </select>
            <input name="target_country" value="{{ $student->target_country }}">
            <select name="lead_source">
                <option value="">Lead Source</option>
                @foreach(['website_form','landing_page','meta_ads','google_ads','whatsapp','instagram','telegram','email','phone_call','education_fair','referral','walk_in','other'] as $src)
                    <option value="{{ $src }}" {{ ($student->lead_source ?? '') === $src ? 'selected' : '' }}>{{ ucwords(str_replace('_',' ', $src)) }}</option>
                @endforeach
            </select>
            <input name="budget_usd" value="{{ $student->budget_usd }}">
            <input name="passport_number" value="{{ $student->passport_number }}">
            <select name="agent_id">
                <option value="">Select Agent</option>
                @foreach($agents as $agent)
                    <option value="{{ $agent->id }}" {{ (string) ($student->agent_id ?? '') === (string) $agent->id ? 'selected' : '' }}>{{ $agent->name }}</option>
                @endforeach
            </select>
            <select name="sub_agent_id">
                <option value="">Select Sub-Agent</option>
                @foreach($subAgents as $sub)
                    <option value="{{ $sub->id }}" {{ (string) ($student->sub_agent_id ?? '') === (string) $sub->id ? 'selected' : '' }}>{{ $sub->name }}</option>
                @endforeach
            </select>
            <select name="is_active">
                <option value="1" {{ (int) $student->is_active === 1 ? 'selected' : '' }}>Active</option>
                <option value="0" {{ (int) $student->is_active === 0 ? 'selected' : '' }}>Inactive</option>
            </select>
        </div>
        <div style="margin-top:10px;display:flex;gap:8px;">
            <button type="submit">Save Changes</button>
            <button type="button" class="secondary" onclick="document.getElementById('editStudent{{ $student->id }}').close()">Cancel</button>
        </div>
    </form>
    <hr style="margin:12px 0;border:none;border-top:1px solid var(--border);">
    <form method="POST" action="/students/{{ $student->id }}/reset-password" class="toolbar">
        @csrf
        <input type="password" name="new_password" placeholder="Reset portal password (min 8)" required>
        <button type="submit" class="secondary">Reset Password</button>
    </form>
</dialog>
@endforeach

<dialog id="addStudent" class="card" style="max-width:760px;">
    <h3 style="margin-top:0;">Add Student</h3>
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
        <div class="grid-4">
            <input name="full_name" placeholder="Full name" required>
            <input name="email" placeholder="Email" required>
            <input name="account_password" type="password" placeholder="Portal password" required>
            <input name="phone" placeholder="Phone">
            <input name="nationality" placeholder="Nationality">
            <input name="gpa" placeholder="GPA">
            <select name="field_of_study">
                <option value="">Field of Study</option>
                @foreach(($studyFields ?? collect()) as $field)
                    <option value="{{ $field }}">{{ $field }}</option>
                @endforeach
            </select>
            <select name="preferred_university_language"><option value="">University language</option><option value="en">English</option><option value="tr">Turkish</option></select>
            <input name="english_level" placeholder="English level">
            <select name="stage">
                @foreach(['lead','inquiry','applicant','documents_pending','interview_scheduled','admitted','visa_process','tuition_paid','enrolled','alumni'] as $st)
                    <option value="{{ $st }}">{{ ucfirst($st) }}</option>
                @endforeach
            </select>
            <select name="lifecycle_stage">
                @foreach(['lead','inquiry','applicant','admitted','enrolled','alumni'] as $lc)
                    <option value="{{ $lc }}">{{ ucfirst($lc) }}</option>
                @endforeach
            </select>
            <input name="target_country" placeholder="Target country">
            <select name="lead_source">
                <option value="">Lead Source</option>
                @foreach(['website_form','landing_page','meta_ads','google_ads','whatsapp','instagram','telegram','email','phone_call','education_fair','referral','walk_in','other'] as $src)
                    <option value="{{ $src }}">{{ ucwords(str_replace('_',' ', $src)) }}</option>
                @endforeach
            </select>
            <input name="budget_usd" placeholder="Budget USD">
            <input name="passport_number" placeholder="Passport #">
            <select name="agent_id">
                <option value="">Select Agent</option>
                @foreach($agents as $agent)
                    <option value="{{ $agent->id }}">{{ $agent->name }}</option>
                @endforeach
            </select>
            <select name="sub_agent_id">
                <option value="">Select Sub-Agent</option>
                @foreach($subAgents as $sub)
                    <option value="{{ $sub->id }}">{{ $sub->name }}</option>
                @endforeach
            </select>
            <select name="is_active"><option value="1">Active login</option><option value="0">Inactive login</option></select>
        </div>
        <div style="margin-top:10px;display:flex;gap:8px;">
            <button type="submit">Save</button>
            <button type="button" class="secondary" onclick="document.getElementById('addStudent').close()">Cancel</button>
        </div>
    </form>
</dialog>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const modal = "{{ old('_modal') }}";
    if (modal === 'addStudent' && document.getElementById('addStudent')) {
        document.getElementById('addStudent').showModal();
    }
});
</script>
 </div>
@endsection
