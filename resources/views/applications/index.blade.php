@extends('layouts.app')

@section('content')
<div class="page-shell">
<div class="card">
    <div class="toolbar">
        <form method="GET" action="/applications" class="toolbar grow">
            <input id="global-search" name="q" value="{{ $q }}" placeholder="Search student/program/university/notes">
            <select name="university_sort">
                <option value="name_asc" {{ ($universitySort ?? 'name_asc') === 'name_asc' ? 'selected' : '' }}>University A-Z</option>
                <option value="name_desc" {{ ($universitySort ?? '') === 'name_desc' ? 'selected' : '' }}>University Z-A</option>
                <option value="created_asc" {{ ($universitySort ?? '') === 'created_asc' ? 'selected' : '' }}>University oldest first</option>
            </select>
            <select name="status">
                <option value="">All statuses</option>
                @foreach(['new_lead','interested','application_started','documents_pending','interview_scheduled','offer_sent','visa_process','tuition_paid','enrolled','rejected'] as $s)
                    <option value="{{ $s }}" {{ $status === $s ? 'selected' : '' }}>{{ ucwords(str_replace('_', ' ', $s)) }}</option>
                @endforeach
            </select>
            <select name="per_page" class="per-page-select" title="Items per page" aria-label="Items per page" onchange="this.form.submit()">
                @foreach([15, 50, 100] as $size)
                    <option value="{{ $size }}" {{ (int)($perPage ?? 50) === $size ? 'selected' : '' }}>{{ $size }}</option>
                @endforeach
            </select>
            <button type="submit">Filter</button>
        </form>
        <button onclick="document.getElementById('addApp').showModal()">+ Add Application</button>
    </div>
    <table class="table-compact">
        <thead><tr><th>ID</th><th>Student</th><th>University</th><th>Program</th><th>Status</th><th>Enroll %</th><th>Next Action</th><th>Actions</th></tr></thead>
        <tbody>
        @foreach($applications as $app)
            <tr>
                <td><a href="/applications/{{ $app->id }}">#{{ $app->id }}</a></td>
                <td>{{ $app->student_name ?: ('#'.$app->student_id) }}</td>
                <td>{{ $app->university_name ?: ('#'.$app->university_id) }}</td>
                <td>{{ $app->program }}</td>
                <td><span class="badge {{ $app->status }}">{{ ucwords(str_replace('_', ' ', $app->status)) }}</span></td>
                <td>{{ $app->enroll_probability }}%</td>
                <td>{{ $app->best_next_action ?: '-' }}</td>
                <td style="display:flex;gap:6px;">
                    <a class="tab" href="/applications/{{ $app->id }}">View</a>
                    <form method="POST" action="/applications/{{ $app->id }}" onsubmit="return confirm('Delete application?')">@csrf @method('DELETE')<button class="secondary">Delete</button></form>
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
    <div class="pagination-wrap">{{ $applications->links() }}</div>
</div>

<dialog id="addApp" class="card" style="max-width:820px;">
    <h3 style="margin-top:0;">Add Application</h3>
    <form method="POST" action="/applications">
        @csrf
        <input type="hidden" name="_modal" value="addApp">
        <div class="grid-4">
                <select name="student_id" required>
                @foreach($students as $s)
                    <option value="{{ $s->id }}">{{ $s->full_name }}</option>
                @endforeach
            </select>
            <select id="app_country" name="country_filter">
                <option value="">Select country (Turkey / Northern Cyprus)</option>
                @foreach($countryOptions as $country)
                    <option value="{{ $country }}">{{ $country }}</option>
                @endforeach
            </select>
            <select id="app_university" name="university_id" required>
                <option value="">Select university</option>
                @foreach($universities as $u)
                    <option value="{{ $u->id }}">{{ $u->name }}</option>
                @endforeach
            </select>
            <select id="app_degree_filter">
                <option value="">Degree filter</option>
                <option value="Diploma">Diploma</option>
                <option value="Associate">Associate</option>
                <option value="Bachelor">Bachelor</option>
                <option value="Master">Master</option>
                <option value="PhD">PhD</option>
            </select>
            <select id="app_thesis_filter">
                <option value="">Thesis filter</option>
                <option value="thesis">Thesis</option>
                <option value="non_thesis">Non-Thesis</option>
                <option value="both">Both</option>
            </select>
            <select id="app_program_language_filter">
                <option value="">Program language</option>
                <option value="English">English</option>
                <option value="Turkish">Turkish</option>
                <option value="Arabic">Arabic</option>
                <option value="Both">Both</option>
            </select>
            <select id="app_program" name="program" required>
                <option value="">Select program</option>
            </select>
            <select name="intake" required>
                @if(($intakeTerms ?? collect())->isEmpty())
                    <option value="{{ date('Y') }}-Fall">{{ date('Y') }}-Fall</option>
                @endif
                @foreach(($intakeTerms ?? collect()) as $term)
                    <option value="{{ $term }}">{{ $term }}</option>
                @endforeach
            </select>
            <select name="status">
                @foreach(['new_lead','interested','application_started','documents_pending','interview_scheduled','offer_sent','visa_process','tuition_paid','enrolled','rejected'] as $s)
                    <option value="{{ $s }}">{{ ucwords(str_replace('_', ' ', $s)) }}</option>
                @endforeach
            </select>
            <input name="deadline" type="date">
            <input name="next_followup_at" type="datetime-local">
        </div>
        <textarea name="notes" rows="4" style="width:100%;margin-top:8px;" placeholder="Notes"></textarea>
        <div style="margin-top:10px;display:flex;gap:8px;">
            <button type="submit">Save</button>
            <button type="button" class="secondary" onclick="document.getElementById('addApp').close()">Cancel</button>
        </div>
    </form>
</dialog>
<script>
(() => {
    const countryEl = document.getElementById('app_country');
    const universityEl = document.getElementById('app_university');
    const degreeEl = document.getElementById('app_degree_filter');
    const thesisEl = document.getElementById('app_thesis_filter');
    const programLanguageEl = document.getElementById('app_program_language_filter');
    const programEl = document.getElementById('app_program');
    if (!countryEl || !universityEl || !programEl) return;

    const fillUniversities = async () => {
        const country = countryEl.value;
        const sortSelect = document.querySelector('select[name="university_sort"]');
        const sortValue = sortSelect ? sortSelect.value : 'name_asc';
        const response = await fetch(`/applications/options/universities?country=${encodeURIComponent(country)}&sort=${encodeURIComponent(sortValue)}`);
        const rows = await response.json();
        universityEl.innerHTML = '<option value="">Select university</option>';
        rows.forEach((row) => {
            const option = document.createElement('option');
            option.value = row.id;
            option.textContent = row.name;
            universityEl.appendChild(option);
        });
        if (rows.length === 1) {
            universityEl.value = String(rows[0].id);
            await fillPrograms();
            return;
        }
        programEl.innerHTML = '<option value="">Select program</option>';
    };

    const fillPrograms = async () => {
        const universityId = universityEl.value;
        if (!universityId) {
            programEl.innerHTML = '<option value="">Select program</option>';
            return;
        }
        const degree = degreeEl ? degreeEl.value : '';
        const thesis = thesisEl ? thesisEl.value : '';
        const language = programLanguageEl ? programLanguageEl.value : '';
        const response = await fetch(`/applications/options/programs?university_id=${encodeURIComponent(universityId)}&degree=${encodeURIComponent(degree)}&thesis_type=${encodeURIComponent(thesis)}&program_language=${encodeURIComponent(language)}`);
        const rows = await response.json();
        programEl.innerHTML = '<option value="">Select program</option>';
        rows.forEach((row) => {
            const option = document.createElement('option');
            option.value = row.program_name;
            const thesisText = row.thesis_type ? ` / ${row.thesis_type.replace('_','-')}` : '';
            option.textContent = row.degree_level ? `${row.program_name} (${row.degree_level}${thesisText})` : row.program_name;
            programEl.appendChild(option);
        });
    };

    countryEl.addEventListener('change', fillUniversities);
    universityEl.addEventListener('change', fillPrograms);
    if (degreeEl) degreeEl.addEventListener('change', fillPrograms);
    if (thesisEl) thesisEl.addEventListener('change', fillPrograms);
    if (programLanguageEl) programLanguageEl.addEventListener('change', fillPrograms);

    if (countryEl.value) {
        fillUniversities();
    } else if (universityEl.value) {
        fillPrograms();
    }
})();
</script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    if ("{{ old('_modal') }}" === 'addApp' && document.getElementById('addApp')) {
        document.getElementById('addApp').showModal();
    }
});
</script>
 </div>
@endsection
