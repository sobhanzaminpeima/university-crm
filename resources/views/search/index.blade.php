@extends('layouts.app')

@section('content')
<div class="page-shell">
<div class="card">
    <div class="panel-head">
        <h2 class="panel-title">Professional Program Search</h2>
    </div>
    <form method="GET" action="/search" class="toolbar" style="display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:8px;">
        <input id="global-search" name="q" value="{{ $keywords ?? $q }}" placeholder="Keywords">

        <select id="country_university" name="country_university">
            <option value="">University country</option>
            @foreach($countryOptions as $co)
                <option value="{{ $co }}" {{ $countryUniversity === $co ? 'selected' : '' }}>{{ $co }}</option>
            @endforeach
        </select>

        <select id="city_university" name="city_university" data-selected="{{ $cityUniversity }}">
            <option value="">University city</option>
        </select>

        <select name="university_type">
            <option value="">Type (University / School)</option>
            <option value="university" {{ $universityType === 'university' ? 'selected' : '' }}>University</option>
            <option value="school" {{ $universityType === 'school' ? 'selected' : '' }}>School</option>
        </select>

        <input name="university_name" value="{{ $universityName }}" placeholder="University name">
        <select id="university_id" name="university_id" data-selected="{{ $universityId }}">
            <option value="">Select university</option>
        </select>

        <select id="degree" name="degree">
            <option value="">Degree</option>
            @foreach($degreeOptions as $degreeOption)
                <option value="{{ $degreeOption }}" {{ $degree === $degreeOption ? 'selected' : '' }}>{{ $degreeOption }}</option>
            @endforeach
        </select>
        <select id="thesis_type" name="thesis_type">
            <option value="">Thesis option</option>
            @foreach($thesisOptions as $key => $label)
                <option value="{{ $key }}" {{ ($thesisType ?? '') === $key ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
        </select>
        <select id="program_language" name="program_language">
            <option value="">Program language</option>
            <option value="English" {{ ($programLanguage ?? '') === 'English' ? 'selected' : '' }}>English</option>
            <option value="Turkish" {{ ($programLanguage ?? '') === 'Turkish' ? 'selected' : '' }}>Turkish</option>
            <option value="Arabic" {{ ($programLanguage ?? '') === 'Arabic' ? 'selected' : '' }}>Arabic</option>
            <option value="Both" {{ ($programLanguage ?? '') === 'Both' ? 'selected' : '' }}>Both</option>
        </select>
        <select id="program_name" name="program_name" data-selected="{{ $programName ?? '' }}">
            <option value="">Program by university</option>
            @foreach(($programOptions ?? []) as $programOption)
                <option value="{{ $programOption }}" {{ ($programName ?? '') === $programOption ? 'selected' : '' }}>{{ $programOption }}</option>
            @endforeach
        </select>

        <select name="study_field">
            <option value="">Study field</option>
            @foreach($studyFieldOptions as $fieldOption)
                <option value="{{ $fieldOption }}" {{ $studyField === $fieldOption ? 'selected' : '' }}>{{ $fieldOption }}</option>
            @endforeach
        </select>

        <select name="stage">
            <option value="">Student stage</option>
            @foreach(['lead','inquiry','applicant','documents_pending','interview_scheduled','admitted','visa_process','tuition_paid','enrolled','alumni'] as $st)
                <option value="{{ $st }}" {{ $stage === $st ? 'selected' : '' }}>{{ ucfirst($st) }}</option>
            @endforeach
        </select>

        <input name="country" value="{{ $country }}" placeholder="Student target country">

        <select name="status">
            <option value="">Application status</option>
            @foreach(['new_lead','interested','application_started','documents_pending','interview_scheduled','offer_sent','visa_process','tuition_paid','enrolled','rejected'] as $s)
                <option value="{{ $s }}" {{ $status === $s ? 'selected' : '' }}>{{ ucwords(str_replace('_', ' ', $s)) }}</option>
            @endforeach
        </select>
        <select name="university_language">
            <option value="">Student preferred uni language</option>
            <option value="en" {{ ($universityLanguage ?? '') === 'en' ? 'selected' : '' }}>English</option>
            <option value="tr" {{ ($universityLanguage ?? '') === 'tr' ? 'selected' : '' }}>Turkish</option>
        </select>

        <div style="display:flex;gap:8px;grid-column:1/-1;">
            <button type="submit">Search</button>
            <a class="secondary" href="/search" style="text-decoration:none;padding:10px 14px;border-radius:10px;">Reset</a>
            <a class="secondary" href="/search?{{ http_build_query(array_merge(request()->query(), ['export' => 'universities'])) }}" style="text-decoration:none;padding:10px 14px;border-radius:10px;">Export Universities</a>
            <a class="secondary" href="/search?{{ http_build_query(array_merge(request()->query(), ['export' => 'programs'])) }}" style="text-decoration:none;padding:10px 14px;border-radius:10px;">Export Programs</a>
        </div>
    </form>
</div>

<div class="three-col" style="margin-top:12px;">
    <div class="card">
        <h3>Students ({{ $students->count() }})</h3>
        @forelse($students as $s)
            <div class="list-card"><a href="/students/{{ $s->id }}">{{ $s->full_name }}</a><br><span class="footer-note">{{ $s->email }}</span></div>
        @empty
            <p class="footer-note">No results.</p>
        @endforelse
    </div>
    <div class="card">
        <h3>Applications ({{ $applications->count() }})</h3>
        @forelse($applications as $a)
            <div class="list-card"><a href="/applications/{{ $a->id }}">#{{ $a->id }} - {{ $a->program }}</a><br><span class="footer-note">{{ ucfirst($a->status) }}</span></div>
        @empty
            <p class="footer-note">No results.</p>
        @endforelse
    </div>
    <div class="card">
        <h3>Universities ({{ $universities->count() }})</h3>
        @forelse($universities as $u)
            @php
                $tuitionTypeLabel = match($u->tuition_fee_type ?? '') {
                    'per_semester' => 'Per semester',
                    'yearly' => 'Yearly',
                    'total_program' => 'Total program',
                    default => '',
                };
            @endphp
            <div class="list-card">
                <a href="/universities/{{ $u->id }}">{{ $u->name }}</a><br>
                <span class="footer-note">{{ $u->country }}</span><br>
                @if($u->tuition_range || $tuitionTypeLabel)
                    <span class="footer-note">{{ $u->tuition_range ?: 'Tuition N/A' }}{{ $tuitionTypeLabel ? ' / '.$tuitionTypeLabel : '' }}</span><br>
                @endif
                <span class="footer-note">{{ \Illuminate\Support\Str::limit($u->programs_summary ?: $u->description, 90) }}</span>
            </div>
        @empty
            <p class="footer-note">No results.</p>
        @endforelse
    </div>
</div>
 </div>

<script>
(() => {
    const cityByCountry = @json($citiesByCountry);
    const countrySelect = document.getElementById('country_university');
    const citySelect = document.getElementById('city_university');
    const universitySelect = document.getElementById('university_id');
    const programSelect = document.getElementById('program_name');
    const degreeSelect = document.getElementById('degree');
    const thesisSelect = document.getElementById('thesis_type');
    const programLanguageSelect = document.getElementById('program_language');
    const selectedUniversity = universitySelect ? universitySelect.dataset.selected : '';
    const selectedProgram = programSelect ? programSelect.dataset.selected : '';
    const universitiesByCountry = @json($universitiesByCountry);
    if (!countrySelect || !citySelect) return;

    const refillCities = () => {
        const selectedCountry = countrySelect.value;
        const selectedCity = citySelect.dataset.selected || '';
        const cities = cityByCountry[selectedCountry] || [];

        citySelect.innerHTML = '<option value="">University city</option>';
        cities.forEach((city) => {
            const option = document.createElement('option');
            option.value = city;
            option.textContent = city;
            if (city === selectedCity) option.selected = true;
            citySelect.appendChild(option);
        });

    };

    const refillUniversities = () => {
        if (!universitySelect) return;
        const selectedCountry = countrySelect.value;
        const universities = universitiesByCountry[selectedCountry] || [];
        universitySelect.innerHTML = '<option value="">Select university</option>';
        universities.forEach((u) => {
            const option = document.createElement('option');
            option.value = u.id;
            option.textContent = u.name;
            if (String(u.id) === String(universitySelect.dataset.selected || selectedUniversity)) {
                option.selected = true;
            }
            universitySelect.appendChild(option);
        });
    };

    const refillPrograms = async () => {
        if (!universitySelect || !programSelect) return;
        const universityId = universitySelect.value;
        if (!universityId) {
            programSelect.innerHTML = '<option value="">Program by university</option>';
            return;
        }
        const degree = degreeSelect ? degreeSelect.value : '';
        const thesis = thesisSelect ? thesisSelect.value : '';
        const programLanguage = programLanguageSelect ? programLanguageSelect.value : '';
        const response = await fetch(`/search/options/programs?university_id=${encodeURIComponent(universityId)}&degree=${encodeURIComponent(degree)}&thesis_type=${encodeURIComponent(thesis)}&program_language=${encodeURIComponent(programLanguage)}`);
        const rows = await response.json();
        programSelect.innerHTML = '<option value="">Program by university</option>';
        rows.forEach((row) => {
            const option = document.createElement('option');
            option.value = row.program_name;
            const thesisText = row.thesis_type ? ` / ${row.thesis_type.replace('_','-')}` : '';
            option.textContent = `${row.program_name}${row.degree_level ? ` (${row.degree_level}${thesisText})` : ''}`;
            if (row.program_name === (programSelect.dataset.selected || selectedProgram)) {
                option.selected = true;
            }
            programSelect.appendChild(option);
        });
    };

    countrySelect.addEventListener('change', () => {
        citySelect.dataset.selected = '';
        if (universitySelect) universitySelect.dataset.selected = '';
        refillCities();
        refillUniversities();
        refillPrograms();
    });

    refillCities();
    refillUniversities();
    refillPrograms();

    if (universitySelect) {
        universitySelect.addEventListener('change', () => {
            programSelect.dataset.selected = '';
            refillPrograms();
        });
    }
    if (thesisSelect) {
        thesisSelect.addEventListener('change', () => refillPrograms());
    }
    if (programLanguageSelect) {
        programLanguageSelect.addEventListener('change', () => refillPrograms());
    }

    if (degreeSelect) {
        degreeSelect.addEventListener('change', () => {
            refillPrograms();
        });
    }
})();
</script>
@endsection
