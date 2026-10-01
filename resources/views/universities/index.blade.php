@extends('layouts.app')

@section('content')
<div class="page-shell">
<div class="card">
    <div class="three-col" style="margin-bottom:10px;">
        <div class="card" style="padding:10px;">
            <div class="footer-note">Programs Total</div>
            <h3 style="margin:4px 0 0;">{{ (int)($programQuality['total_programs'] ?? 0) }}</h3>
        </div>
        <div class="card" style="padding:10px;">
            <div class="footer-note">Missing Fee / Language</div>
            <h3 style="margin:4px 0 0;">{{ (int)($programQuality['missing_fee'] ?? 0) }} / {{ (int)($programQuality['missing_language'] ?? 0) }}</h3>
        </div>
        <div class="card" style="padding:10px;">
            <div class="footer-note">Missing Thesis Type</div>
            <h3 style="margin:4px 0 0;">{{ (int)($programQuality['missing_thesis'] ?? 0) }}</h3>
        </div>
    </div>
    <div class="panel university-tools">
        <div class="panel-head university-tools-head">
        <form method="GET" action="/universities" class="university-search">
            <input id="global-search" name="q" value="{{ $q }}" placeholder="Search by university/country/city/type">
            <select name="country">
                <option value="">All countries</option>
                @foreach($countryOptions as $countryOption)
                    <option value="{{ $countryOption }}" {{ ($country ?? '') === $countryOption ? 'selected' : '' }}>{{ $countryOption }}</option>
                @endforeach
            </select>
            <input type="date" name="date_from" value="{{ $dateFrom ?? '' }}" title="From date" aria-label="From date">
            <input type="date" name="date_to" value="{{ $dateTo ?? '' }}" title="To date" aria-label="To date">
            <select name="sort">
                <option value="created_desc" {{ ($sort ?? 'created_desc') === 'created_desc' ? 'selected' : '' }}>Newest first</option>
                <option value="created_asc" {{ ($sort ?? '') === 'created_asc' ? 'selected' : '' }}>Oldest first</option>
                <option value="name_asc" {{ ($sort ?? '') === 'name_asc' ? 'selected' : '' }}>Name A-Z</option>
                <option value="name_desc" {{ ($sort ?? '') === 'name_desc' ? 'selected' : '' }}>Name Z-A</option>
            </select>
            <select name="per_page" class="per-page-select" title="Items per page" aria-label="Items per page" onchange="this.form.submit()">
                @foreach([15, 50, 100] as $size)
                    <option value="{{ $size }}" {{ (int)($perPage ?? 50) === $size ? 'selected' : '' }}>{{ $size }}</option>
                @endforeach
            </select>
            <button type="submit" class="icon-action" title="Search" aria-label="Search">&#128269;</button>
        </form>
        <div class="action-cluster">
            <div class="uni-view-toggle">
                <button type="button" id="uniViewGrid" class="secondary icon-action" title="Grid view" aria-label="Grid view">&#9638;</button>
                <button type="button" id="uniViewList" class="secondary icon-action" title="List view" aria-label="List view">&#9776;</button>
            </div>
            <a class="secondary icon-action" href="/universities/template/export" title="Download CSV template" aria-label="Download CSV template">&#8681;</a>
            <form method="POST" action="/universities/import" enctype="multipart/form-data" class="inline-upload">
                @csrf
                <input type="file" name="file" accept=".csv,.txt" required>
                <button type="submit" class="icon-action" title="Import bulk CSV" aria-label="Import bulk CSV">&#8682;</button>
            </form>
            <form method="POST" action="/universities/deduplicate" onsubmit="return confirm('Remove duplicate universities and programs now?')" style="display:flex;">
                @csrf
                <button type="submit" class="secondary icon-action" title="Remove duplicate universities and programs" aria-label="Remove duplicate universities and programs">&#8635;</button>
            </form>
            <form method="POST" action="/universities/delete-all" onsubmit="return confirm('Delete all universities for this tenant?')" style="display:flex;">
                @csrf
                <button type="submit" class="secondary icon-action danger-action" title="Delete all" aria-label="Delete all">&#128465;</button>
            </form>
            <button type="button" class="icon-action" title="Add university" aria-label="Add university" onclick="document.getElementById('addUniversity').showModal()">+</button>
        </div>
        </div>
    </div>
    <form method="POST" action="/universities/bulk-delete" id="bulkDeleteForm" onsubmit="return confirm('Delete selected universities?')" style="margin:0;">
        @csrf
    </form>
        <div class="bulk-strip">
            <label style="display:flex;align-items:center;gap:6px;">
                <input type="checkbox" id="selectAllUniversities">
                <strong>Select All Universities</strong>
            </label>
            <button type="submit" form="bulkDeleteForm" class="secondary icon-action danger-action" title="Delete selected" aria-label="Delete selected">&#128465;</button>
        </div>
    <div id="universitiesWrap" class="grid-4">
        @foreach($universities as $u)
            <div class="card university-item">
                <label style="display:flex;align-items:center;gap:6px;margin-bottom:8px;">
                    <input type="checkbox" class="uni-select-item" name="ids[]" value="{{ $u->id }}" form="bulkDeleteForm">
                    <span class="footer-note">Select</span>
                </label>
                @if($u->logo_url || $u->image_url)
                    <img class="university-item-image" src="{{ $u->logo_url ?: $u->image_url }}" alt="{{ $u->name }}" style="width:100%;height:120px;object-fit:cover;border-radius:10px;margin-bottom:8px;">
                @endif
                <h3 style="margin:0 0 6px;font-size:14px;color:var(--text)">{{ $u->name }}</h3>
                <div class="footer-note">{{ $u->country }} / {{ $u->city ?: '-' }} - {{ ucfirst($u->institution_type ?: 'university') }} - {{ $u->currency }}</div>
                <p>{{ $u->tuition_range }}</p>
                <p class="footer-note">{{ \Illuminate\Support\Str::limit($u->description, 150) }}</p>
                <p class="footer-note">Applications: {{ $applicationCounts[$u->id] ?? 0 }}</p>
                <div style="display:flex;gap:8px;align-items:center;">
                    <a class="secondary icon-action" href="/universities/{{ $u->id }}" title="More info" aria-label="More info">&#9432;</a>
                    <button type="button" class="secondary icon-action" title="Edit" aria-label="Edit" onclick="document.getElementById('editUniversity{{ $u->id }}').showModal()">&#9998;</button>
                    <form method="POST" action="/universities/{{ $u->id }}" onsubmit="return confirm('Delete university?')">
                        @csrf @method('DELETE')
                        <button class="secondary icon-action danger-action" title="Delete" aria-label="Delete">&#128465;</button>
                    </form>
                </div>
            </div>
        @endforeach
    </div>
    <div class="pagination-wrap uni-pagination">{{ $universities->links() }}</div>
</div>
 </div>

<dialog id="addUniversity" class="card" style="max-width:880px;">
    <h3 style="margin-top:0;">Add University</h3>
    <form method="POST" action="/universities" enctype="multipart/form-data">
        @csrf
        <div class="grid-4">
            <input name="name" placeholder="University name" required>
            <select name="country" required>
                <option value="">Select country</option>
                <option value="Turkey">Turkey</option>
                <option value="North Cyprus">North Cyprus</option>
            </select>
            <input name="city" placeholder="City">
            <select name="institution_type" required>
                <option value="university">University</option>
                <option value="school">School</option>
            </select>
            <input name="website" placeholder="Website">
            <select name="currency">@foreach($currencies as $currency)<option value="{{ $currency }}">{{ $currency }}</option>@endforeach</select>
            <input name="tuition_range" placeholder="Tuition range">
            <select name="language">
                <option value="">University language</option>
                <option value="English">English</option>
                <option value="Turkish">Turkish</option>
                <option value="Arabic">Arabic</option>
                <option value="Both">Both</option>
            </select>
            <select name="tuition_fee_type">
                <option value="">Tuition type</option>
                <option value="per_semester">Per semester</option>
                <option value="yearly">Yearly</option>
                <option value="total_program">Total program</option>
            </select>
            <input name="deadline" type="date">
        </div>
        <textarea name="programs_summary" rows="4" style="width:100%;margin-top:8px;" placeholder="Programs"></textarea>
        <div class="grid-4" style="margin-top:8px;">
            <select name="program_degree_level">
                <option value="">Degree level</option>
                <option value="Diploma">Diploma</option>
                <option value="Associate">Associate</option>
                <option value="Bachelor">Bachelor</option>
                <option value="Master">Master</option>
                <option value="PhD">PhD</option>
            </select>
            <select name="program_thesis_type">
                <option value="">Thesis type</option>
                <option value="thesis">Thesis</option>
                <option value="non_thesis">Non-Thesis</option>
                <option value="both">Both</option>
            </select>
            <input name="program_name" placeholder="Program name (optional)">
            <select name="program_language">
                <option value="">Program language</option>
                <option value="English">English</option>
                <option value="Turkish">Turkish</option>
                <option value="Arabic">Arabic</option>
                <option value="Both">Both</option>
            </select>
            <input name="program_fee" type="number" step="0.01" min="0" placeholder="Program fee">
            <select name="program_fee_type">
                <option value="">Program fee type</option>
                <option value="per_semester">Per semester</option>
                <option value="yearly">Yearly</option>
                <option value="total_program">Total program</option>
            </select>
        </div>
        <div class="card" style="margin-top:8px;padding:8px;">
            <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;">
                <div class="footer-note">Programs (add multiple)</div>
                <button type="button" class="secondary" onclick="addProgramRow(this)">+ Program Row</button>
            </div>
            <div class="program-rows" data-next-index="1" style="margin-top:8px;">
                <div class="grid-4 program-row" style="margin-bottom:8px;">
                    <input name="programs[0][program_name]" placeholder="Program name">
                    <select name="programs[0][program_degree_level]"><option value="">Degree</option><option value="Diploma">Diploma</option><option value="Associate">Associate</option><option value="Bachelor">Bachelor</option><option value="Master">Master</option><option value="PhD">PhD</option></select>
                    <select name="programs[0][program_thesis_type]"><option value="">Thesis</option><option value="thesis">Thesis</option><option value="non_thesis">Non-Thesis</option><option value="both">Both</option></select>
                    <select name="programs[0][program_language]"><option value="">Language</option><option value="English">English</option><option value="Turkish">Turkish</option><option value="Arabic">Arabic</option><option value="Both">Both</option></select>
                    <input name="programs[0][program_duration]" placeholder="Duration">
                    <input name="programs[0][program_currency]" placeholder="Currency">
                    <input name="programs[0][program_fee]" type="number" step="0.01" min="0" placeholder="Fee">
                    <select name="programs[0][program_fee_type]"><option value="">Fee Type</option><option value="per_semester">Per semester</option><option value="yearly">Yearly</option><option value="total_program">Total program</option></select>
                    <input name="programs[0][program_notes]" placeholder="Notes">
                    <button type="button" class="secondary" onclick="this.closest('.program-row').remove()">Remove</button>
                </div>
            </div>
        </div>
        <div class="two-col" style="margin-top:8px;">
            <input type="file" name="logo" accept=".jpg,.jpeg,.png,.webp">
            <input type="file" name="image" accept=".jpg,.jpeg,.png,.webp">
        </div>
        <textarea name="visa_notes" rows="3" style="width:100%;margin-top:8px;" placeholder="Visa notes"></textarea>
        <textarea name="description" rows="4" style="width:100%;margin-top:8px;" placeholder="Full details"></textarea>
        <div style="margin-top:10px;display:flex;gap:8px;">
            <button type="submit">Save</button>
            <button type="button" class="secondary" onclick="document.getElementById('addUniversity').close()">Cancel</button>
        </div>
    </form>
</dialog>

@foreach($universities as $u)
<dialog id="editUniversity{{ $u->id }}" class="card" style="max-width:880px;">
    <h3 style="margin-top:0;">Edit University</h3>
    @php
        $existingPrograms = $programsByUniversity[$u->id] ?? [];
        $firstProgram = $existingPrograms[0] ?? null;
    @endphp
    <form method="POST" action="/universities/{{ $u->id }}" enctype="multipart/form-data">
        @csrf
        @method('PUT')
        <input type="hidden" name="program_id" value="{{ $firstProgram->id ?? '' }}">
        <div class="deleted-program-ids"></div>
        <div class="grid-4">
            <input name="name" value="{{ $u->name }}" required>
            <input name="country" value="{{ $u->country }}" required>
            <input name="city" value="{{ $u->city }}">
            <select name="institution_type" required>
                <option value="university" {{ ($u->institution_type ?? 'university') === 'university' ? 'selected' : '' }}>University</option>
                <option value="school" {{ ($u->institution_type ?? '') === 'school' ? 'selected' : '' }}>School</option>
            </select>
            <input name="website" value="{{ $u->website }}">
            <select name="currency">@foreach($currencies as $currency)<option value="{{ $currency }}" {{ $u->currency === $currency ? 'selected' : '' }}>{{ $currency }}</option>@endforeach</select>
            <input name="tuition_range" value="{{ $u->tuition_range }}">
            <select name="language">
                <option value="">University language</option>
                <option value="English" {{ $u->language === 'English' ? 'selected' : '' }}>English</option>
                <option value="Turkish" {{ $u->language === 'Turkish' ? 'selected' : '' }}>Turkish</option>
                <option value="Arabic" {{ $u->language === 'Arabic' ? 'selected' : '' }}>Arabic</option>
                <option value="Both" {{ $u->language === 'Both' ? 'selected' : '' }}>Both</option>
            </select>
            <select name="tuition_fee_type">
                <option value="">Tuition type</option>
                <option value="per_semester" {{ ($u->tuition_fee_type ?? '') === 'per_semester' ? 'selected' : '' }}>Per semester</option>
                <option value="yearly" {{ ($u->tuition_fee_type ?? '') === 'yearly' ? 'selected' : '' }}>Yearly</option>
                <option value="total_program" {{ ($u->tuition_fee_type ?? '') === 'total_program' ? 'selected' : '' }}>Total program</option>
            </select>
            <input name="deadline" type="date" value="{{ $u->deadline }}">
            <select name="is_active"><option value="1" {{ (int) $u->is_active === 1 ? 'selected' : '' }}>Active</option><option value="0" {{ (int) $u->is_active === 0 ? 'selected' : '' }}>Inactive</option></select>
        </div>
        <textarea name="programs_summary" rows="4" style="width:100%;margin-top:8px;">{{ $u->programs_summary ?: $u->description }}</textarea>
        <div class="grid-4" style="margin-top:8px;">
            <select name="program_degree_level">
                <option value="">Degree level</option>
                <option value="Diploma" {{ ($firstProgram->degree_level ?? '') === 'Diploma' ? 'selected' : '' }}>Diploma</option>
                <option value="Associate" {{ ($firstProgram->degree_level ?? '') === 'Associate' ? 'selected' : '' }}>Associate</option>
                <option value="Bachelor" {{ ($firstProgram->degree_level ?? '') === 'Bachelor' ? 'selected' : '' }}>Bachelor</option>
                <option value="Master" {{ ($firstProgram->degree_level ?? '') === 'Master' ? 'selected' : '' }}>Master</option>
                <option value="PhD" {{ ($firstProgram->degree_level ?? '') === 'PhD' ? 'selected' : '' }}>PhD</option>
            </select>
            <select name="program_thesis_type">
                <option value="">Thesis type</option>
                <option value="thesis" {{ ($firstProgram->thesis_type ?? '') === 'thesis' ? 'selected' : '' }}>Thesis</option>
                <option value="non_thesis" {{ ($firstProgram->thesis_type ?? '') === 'non_thesis' ? 'selected' : '' }}>Non-Thesis</option>
                <option value="both" {{ ($firstProgram->thesis_type ?? '') === 'both' ? 'selected' : '' }}>Both</option>
            </select>
            <input name="program_name" value="{{ $firstProgram->program_name ?? '' }}" placeholder="Add/Update program name">
            <select name="program_language">
                <option value="">Program language</option>
                <option value="English" {{ ($firstProgram->language ?? '') === 'English' ? 'selected' : '' }}>English</option>
                <option value="Turkish" {{ ($firstProgram->language ?? '') === 'Turkish' ? 'selected' : '' }}>Turkish</option>
                <option value="Arabic" {{ ($firstProgram->language ?? '') === 'Arabic' ? 'selected' : '' }}>Arabic</option>
                <option value="Both" {{ ($firstProgram->language ?? '') === 'Both' ? 'selected' : '' }}>Both</option>
            </select>
            <input name="program_fee" type="number" step="0.01" min="0" value="{{ $firstProgram->fee ?? '' }}" placeholder="Program fee">
            <input name="program_duration" value="{{ $firstProgram->duration ?? '' }}" placeholder="Program duration">
            <input name="program_currency" value="{{ $firstProgram->currency ?? '' }}" placeholder="Program currency">
            <select name="program_fee_type">
                <option value="">Program fee type</option>
                <option value="per_semester" {{ ($firstProgram->fee_type ?? '') === 'per_semester' ? 'selected' : '' }}>Per semester</option>
                <option value="yearly" {{ ($firstProgram->fee_type ?? '') === 'yearly' ? 'selected' : '' }}>Yearly</option>
                <option value="total_program" {{ ($firstProgram->fee_type ?? '') === 'total_program' ? 'selected' : '' }}>Total program</option>
            </select>
        </div>
        <textarea name="program_notes" rows="2" style="width:100%;margin-top:8px;" placeholder="Program notes">{{ $firstProgram->notes ?? '' }}</textarea>
        @if(!empty($existingPrograms))
        <div class="card" style="margin-top:8px;padding:8px;">
            <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;">
                <div class="footer-note" style="margin-bottom:6px;">Programs (edit/add/delete)</div>
                <button type="button" class="secondary" onclick="addProgramRow(this)">+ Program Row</button>
            </div>
            <div class="program-rows" data-next-index="{{ count($existingPrograms) }}">
                @foreach($existingPrograms as $idx => $prog)
                <div class="grid-4 program-row" style="margin-bottom:8px;">
                    <input type="hidden" name="programs[{{ $idx }}][program_id]" value="{{ $prog->id }}">
                    <input name="programs[{{ $idx }}][program_name]" value="{{ $prog->program_name }}" placeholder="Program name">
                    <select name="programs[{{ $idx }}][program_degree_level]"><option value="">Degree</option><option value="Diploma" {{ ($prog->degree_level ?? '') === 'Diploma' ? 'selected' : '' }}>Diploma</option><option value="Associate" {{ ($prog->degree_level ?? '') === 'Associate' ? 'selected' : '' }}>Associate</option><option value="Bachelor" {{ ($prog->degree_level ?? '') === 'Bachelor' ? 'selected' : '' }}>Bachelor</option><option value="Master" {{ ($prog->degree_level ?? '') === 'Master' ? 'selected' : '' }}>Master</option><option value="PhD" {{ ($prog->degree_level ?? '') === 'PhD' ? 'selected' : '' }}>PhD</option></select>
                    <select name="programs[{{ $idx }}][program_thesis_type]"><option value="">Thesis</option><option value="thesis" {{ ($prog->thesis_type ?? '') === 'thesis' ? 'selected' : '' }}>Thesis</option><option value="non_thesis" {{ ($prog->thesis_type ?? '') === 'non_thesis' ? 'selected' : '' }}>Non-Thesis</option><option value="both" {{ ($prog->thesis_type ?? '') === 'both' ? 'selected' : '' }}>Both</option></select>
                    <select name="programs[{{ $idx }}][program_language]"><option value="">Language</option><option value="English" {{ ($prog->language ?? '') === 'English' ? 'selected' : '' }}>English</option><option value="Turkish" {{ ($prog->language ?? '') === 'Turkish' ? 'selected' : '' }}>Turkish</option><option value="Arabic" {{ ($prog->language ?? '') === 'Arabic' ? 'selected' : '' }}>Arabic</option><option value="Both" {{ ($prog->language ?? '') === 'Both' ? 'selected' : '' }}>Both</option></select>
                    <input name="programs[{{ $idx }}][program_duration]" value="{{ $prog->duration ?? '' }}" placeholder="Duration">
                    <input name="programs[{{ $idx }}][program_currency]" value="{{ $prog->currency ?? '' }}" placeholder="Currency">
                    <input name="programs[{{ $idx }}][program_fee]" type="number" step="0.01" min="0" value="{{ $prog->fee ?? '' }}" placeholder="Fee">
                    <select name="programs[{{ $idx }}][program_fee_type]"><option value="">Fee Type</option><option value="per_semester" {{ ($prog->fee_type ?? '') === 'per_semester' ? 'selected' : '' }}>Per semester</option><option value="yearly" {{ ($prog->fee_type ?? '') === 'yearly' ? 'selected' : '' }}>Yearly</option><option value="total_program" {{ ($prog->fee_type ?? '') === 'total_program' ? 'selected' : '' }}>Total program</option></select>
                    <input name="programs[{{ $idx }}][program_notes]" value="{{ $prog->notes ?? '' }}" placeholder="Notes">
                    <button type="button" class="secondary" onclick="removeProgramRow(this)">Remove</button>
                </div>
                @endforeach
            </div>
            <div style="max-height:180px;overflow:auto;">
                <table class="table-compact">
                    <thead><tr><th>Program</th><th>Language</th><th>Fee</th><th>Action</th></tr></thead>
                    <tbody>
                    @foreach($existingPrograms as $prog)
                        <tr>
                            <td>{{ $prog->program_name }}</td>
                            <td>{{ $prog->language ?: '-' }}</td>
                            <td>{{ $prog->fee ?? '-' }} {{ $prog->currency ?? '' }}</td>
                            <td>
                                <div style="display:flex;gap:6px;align-items:center;">
                                    <button type="button" class="secondary load-program-btn"
                                        data-program-id="{{ $prog->id }}"
                                        data-degree="{{ $prog->degree_level }}"
                                        data-thesis="{{ $prog->thesis_type }}"
                                        data-name="{{ $prog->program_name }}"
                                        data-language="{{ $prog->language }}"
                                        data-fee="{{ $prog->fee }}"
                                        data-duration="{{ $prog->duration }}"
                                        data-currency="{{ $prog->currency }}"
                                        data-fee-type="{{ $prog->fee_type }}"
                                        data-notes="{{ $prog->notes }}">Load</button>
                                    <button type="button" class="secondary" data-program-id="{{ $prog->id }}" onclick="removeProgramRow(this)" title="Remove Program" aria-label="Remove Program">Remove</button>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif
        <div class="two-col" style="margin-top:8px;">
            <input type="file" name="logo" accept=".jpg,.jpeg,.png,.webp">
            <input type="file" name="image" accept=".jpg,.jpeg,.png,.webp">
        </div>
        <textarea name="visa_notes" rows="3" style="width:100%;margin-top:8px;">{{ $u->visa_notes }}</textarea>
        <textarea name="description" rows="4" style="width:100%;margin-top:8px;">{{ $u->description ?: $u->programs_summary }}</textarea>
        <div style="margin-top:10px;display:flex;gap:8px;">
            <button type="submit">Update</button>
            <button type="button" class="secondary" onclick="document.getElementById('editUniversity{{ $u->id }}').close()">Cancel</button>
        </div>
    </form>
</dialog>
@endforeach
<script>
document.addEventListener('DOMContentLoaded', function () {
    const wrap = document.getElementById('universitiesWrap');
    const btnGrid = document.getElementById('uniViewGrid');
    const btnList = document.getElementById('uniViewList');
    if (!wrap || !btnGrid || !btnList) return;
    const key = 'universities_view_mode';
    const applyMode = function (mode) {
        const isList = mode === 'list';
        wrap.classList.toggle('university-list', isList);
        btnGrid.classList.toggle('active', !isList);
        btnList.classList.toggle('active', isList);
        localStorage.setItem(key, isList ? 'list' : 'grid');
    };
    btnGrid.addEventListener('click', function () { applyMode('grid'); });
    btnList.addEventListener('click', function () { applyMode('list'); });
    applyMode(localStorage.getItem(key) === 'list' ? 'list' : 'grid');

    const selectAllEl = document.getElementById('selectAllUniversities');
    const itemEls = Array.from(document.querySelectorAll('.uni-select-item'));
    if (selectAllEl && itemEls.length) {
        selectAllEl.addEventListener('change', function () {
            itemEls.forEach((el) => { el.checked = selectAllEl.checked; });
        });
        itemEls.forEach((el) => {
            el.addEventListener('change', function () {
                const selected = itemEls.filter((x) => x.checked).length;
                selectAllEl.checked = selected === itemEls.length;
                selectAllEl.indeterminate = selected > 0 && selected < itemEls.length;
            });
        });
    }
    document.querySelectorAll('.load-program-btn').forEach((btn) => {
        btn.addEventListener('click', function () {
            const form = btn.closest('form');
            if (!form) return;
            const programIdInput = form.querySelector('[name=\"program_id\"]');
            if (programIdInput) programIdInput.value = btn.dataset.programId || '';
            form.querySelector('[name=\"program_degree_level\"]').value = btn.dataset.degree || '';
            form.querySelector('[name=\"program_thesis_type\"]').value = btn.dataset.thesis || '';
            form.querySelector('[name=\"program_name\"]').value = btn.dataset.name || '';
            form.querySelector('[name=\"program_language\"]').value = btn.dataset.language || '';
            form.querySelector('[name=\"program_fee\"]').value = btn.dataset.fee || '';
            form.querySelector('[name=\"program_duration\"]').value = btn.dataset.duration || '';
            form.querySelector('[name=\"program_currency\"]').value = btn.dataset.currency || '';
            form.querySelector('[name=\"program_fee_type\"]').value = btn.dataset.feeType || '';
            form.querySelector('[name=\"program_notes\"]').value = btn.dataset.notes || '';
        });
    });
});
function addProgramRow(triggerBtn) {
    const box = triggerBtn.closest('.card').querySelector('.program-rows');
    if (!box) return;
    const idx = parseInt(box.dataset.nextIndex || '0', 10);
    box.dataset.nextIndex = String(idx + 1);
    const row = document.createElement('div');
    row.className = 'grid-4 program-row';
    row.style.marginBottom = '8px';
    row.innerHTML = `
        <input name="programs[${idx}][program_name]" placeholder="Program name">
        <select name="programs[${idx}][program_degree_level]"><option value="">Degree</option><option value="Diploma">Diploma</option><option value="Associate">Associate</option><option value="Bachelor">Bachelor</option><option value="Master">Master</option><option value="PhD">PhD</option></select>
        <select name="programs[${idx}][program_thesis_type]"><option value="">Thesis</option><option value="thesis">Thesis</option><option value="non_thesis">Non-Thesis</option><option value="both">Both</option></select>
        <select name="programs[${idx}][program_language]"><option value="">Language</option><option value="English">English</option><option value="Turkish">Turkish</option><option value="Arabic">Arabic</option><option value="Both">Both</option></select>
        <input name="programs[${idx}][program_duration]" placeholder="Duration">
        <input name="programs[${idx}][program_currency]" placeholder="Currency">
        <input name="programs[${idx}][program_fee]" type="number" step="0.01" min="0" placeholder="Fee">
        <select name="programs[${idx}][program_fee_type]"><option value="">Fee Type</option><option value="per_semester">Per semester</option><option value="yearly">Yearly</option><option value="total_program">Total program</option></select>
        <input name="programs[${idx}][program_notes]" placeholder="Notes">
        <button type="button" class="secondary" onclick="this.closest('.program-row').remove()">Remove</button>
    `;
    box.appendChild(row);
}
function removeProgramRow(btn) {
    if (!confirm('Remove this program?')) return;
    const row = btn.closest('.program-row') || btn.closest('tr');
    if (!row) return;
    const form = btn.closest('form');
    const hiddenProgramId = row.querySelector('input[name$="[program_id]"]');
    const programIdValue = (hiddenProgramId && hiddenProgramId.value) ? hiddenProgramId.value : (btn.dataset.programId || '');
    if (form && programIdValue) {
        const bag = form.querySelector('.deleted-program-ids');
        if (bag) {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'deleted_program_ids[]';
            input.value = programIdValue;
            bag.appendChild(input);
        }
    }
    row.remove();
}
</script>
@endsection
