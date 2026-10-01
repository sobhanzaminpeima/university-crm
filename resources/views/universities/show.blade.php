@extends('layouts.app')

@section('content')
<div class="page-shell">
<div class="card university-details">
    <div class="uni-hero">
        <h2 style="margin:0;">{{ $university->name }}</h2>
        <div class="uni-meta-chips">
            <span class="badge">{{ $university->country }}</span>
            <span class="badge">{{ $university->city ?: 'City N/A' }}</span>
            <span class="badge">{{ ucfirst($university->institution_type ?: 'university') }}</span>
            <span class="badge">{{ $university->currency }}</span>
            <span class="badge">{{ $university->language ?: 'Language N/A' }}</span>
        </div>
    </div>

    <div class="two-col" style="margin-top:10px;">
        <div class="card" style="padding:12px;">
            <h3 style="margin-top:0;">Overview</h3>
            <p><strong>Website:</strong>
                @if($university->website)
                    <a href="{{ $university->website }}" target="_blank" rel="noopener">{{ $university->website }}</a>
                @else
                    -
                @endif
            </p>
            <p><strong>Tuition Range:</strong> {{ $university->tuition_range ?: '-' }}</p>
            <p><strong>Deadline:</strong> {{ $university->deadline ?: '-' }}</p>
        </div>
        <div class="card" style="padding:12px;">
            <h3 style="margin-top:0;">Visa / Notes</h3>
            <div class="uni-richtext">{!! $university->visa_notes ? nl2br(e($university->visa_notes)) : 'No visa notes.' !!}</div>
        </div>
    </div>

    <div class="card" style="padding:12px;margin-top:10px;">
        <h3 style="margin-top:0;">Programs</h3>
        @if(($programs ?? collect())->count() > 0)
            <table class="table-compact">
                <thead>
                <tr>
                    <th>Degree</th>
                    <th>Program</th>
                    <th>Language</th>
                    <th>Duration</th>
                    <th>Fee</th>
                    <th>Notes</th>
                </tr>
                </thead>
                <tbody>
                @foreach($programs as $p)
                    <tr>
                        <td>{{ $p->degree_level ?: '-' }}</td>
                        <td>{{ $p->program_name ?: '-' }}</td>
                        <td>{{ $p->language ?: '-' }}</td>
                        <td>{{ $p->duration ?: '-' }}</td>
                        <td>
                            @if($p->fee !== null)
                                {{ $p->currency ?: $university->currency }} {{ number_format((float)$p->fee, 2) }}
                            @else
                                -
                            @endif
                        </td>
                        <td>{{ $p->notes ?: '-' }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @else
            <div class="uni-richtext">{!! $university->programs_summary ? nl2br(e($university->programs_summary)) : 'No structured programs found.' !!}</div>
        @endif
    </div>

    <div class="card" style="padding:12px;margin-top:10px;">
        <h3 style="margin-top:0;">Required Documents</h3>
        <p class="footer-note">Documents students must submit for applications to this university. Applied automatically to the checklist on every application here.</p>
        @if(($documentRequirements ?? collect())->count() > 0)
            <table class="table-compact" style="margin-bottom:10px;">
                <thead><tr><th>Document</th><th>Label</th><th>Required</th><th></th></tr></thead>
                <tbody>
                @foreach($documentRequirements as $req)
                    <tr>
                        <td>{{ ucwords(str_replace('_',' ', $req->doc_type)) }}</td>
                        <td>{{ $req->label }}</td>
                        <td>{{ $req->is_mandatory ? 'Mandatory' : 'Optional' }}</td>
                        <td>
                            <form method="POST" action="/universities/{{ $university->id }}/document-requirements/{{ $req->id }}" onsubmit="return confirm('Remove this requirement?')">
                                @csrf
                                @method('DELETE')
                                <button class="secondary danger-action" type="submit">Remove</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif
        <form method="POST" action="/universities/{{ $university->id }}/document-requirements">
            @csrf
            <div class="grid-4">
                <select name="doc_type" required>
                    <option value="passport">Passport</option>
                    <option value="diploma">Diploma</option>
                    <option value="transcript">Transcript</option>
                    <option value="english_certificate">English Certificate</option>
                    <option value="photo">Photo</option>
                    <option value="payment_receipt">Payment Receipt</option>
                    <option value="acceptance_letter">Acceptance Letter</option>
                    <option value="other_documents">Other Documents</option>
                </select>
                <input name="label" placeholder="Label shown to staff (e.g. Notarized Diploma)" required>
                <label style="display:flex;align-items:center;gap:6px;"><input type="checkbox" name="is_mandatory" value="1" checked> Mandatory</label>
                <button type="submit">Add Requirement</button>
            </div>
        </form>
    </div>

    <div class="card" style="padding:12px;margin-top:10px;">
        <h3 style="margin-top:0;">Description</h3>
        <div class="uni-richtext">{!! $university->description ? nl2br(e($university->description)) : 'No description.' !!}</div>
    </div>
</div>
 </div>
@endsection
