@extends('layouts.app')

@section('content')
<div class="page-shell">
<div class="card">
    <div style="display:flex;justify-content:space-between;gap:12px;align-items:flex-start;">
        <div>
            <h2 style="margin-top:0;">Application #{{ $application->id }}</h2>
            <p><strong>Status:</strong> <span class="badge {{ $application->status }}">{{ ucwords(str_replace('_', ' ', $application->status)) }}</span></p>
        </div>
        <a class="tab" href="/applications">Back to list</a>
    </div>

    <div class="card app-case-wide">
        <h3>Case Details</h3>
        <div class="app-case-table">
                <div class="app-case-item">
                    <div class="app-case-name">Student</div>
                    <div class="app-case-data">{{ $student?->full_name ?: ('#'.$application->student_id) }}</div>
                </div>
                <div class="app-case-item">
                    <div class="app-case-name">University</div>
                    <div class="app-case-data">{{ $university?->name ?: ('#'.$application->university_id) }}</div>
                </div>
                <div class="app-case-item">
                    <div class="app-case-name">Program</div>
                    <div class="app-case-data">{{ $application->program }}</div>
                </div>
                <div class="app-case-item">
                    <div class="app-case-name">Intake</div>
                    <div class="app-case-data">{{ $application->intake }}</div>
                </div>
                <div class="app-case-item">
                    <div class="app-case-name">Deadline</div>
                    <div class="app-case-data">{{ $application->deadline ?: '-' }}</div>
                </div>
                <div class="app-case-item">
                    <div class="app-case-name">Next Follow-up</div>
                    <div class="app-case-data">{{ $application->next_followup_at ?: '-' }}</div>
                </div>
                <div class="app-case-item">
                    <div class="app-case-name">Enroll Probability</div>
                    <div class="app-case-data">{{ $application->enroll_probability }}%</div>
                </div>
                <div class="app-case-item">
                    <div class="app-case-name">Explainability</div>
                    <div class="app-case-data">{{ $application->explainability ?: '-' }}</div>
                </div>
                <div class="app-case-item">
                    <div class="app-case-name">Best Next Action</div>
                    <div class="app-case-data">{{ $application->best_next_action ?: '-' }}</div>
                </div>
        </div>
        <h3>Notes</h3>
        <pre style="white-space:pre-wrap;font-family:inherit;line-height:1.6;font-size:14px;">{{ $application->notes ?: '-' }}</pre>
    </div>

    <div class="card" style="margin-top:12px;">
        <h3>Document Checklist</h3>
        @if($checklist->isEmpty())
            <p class="footer-note">No document requirements configured for {{ $university?->name ?: 'this university' }} yet. <a href="/universities/{{ $application->university_id }}">Set them up here</a>.</p>
        @else
            <table class="table-compact">
                <thead>
                    <tr><th>Document</th><th>Required</th><th>Status</th><th>Note</th><th>Actions</th></tr>
                </thead>
                <tbody>
                @foreach($checklist as $item)
                    <tr>
                        <td>{{ $item->requirement->label }}</td>
                        <td>{{ $item->requirement->is_mandatory ? 'Mandatory' : 'Optional' }}</td>
                        <td><span class="badge {{ $item->status }}">{{ ucwords(str_replace('_', ' ', $item->status)) }}</span></td>
                        <td>{{ $item->document?->review_note ?: '-' }}</td>
                        <td>
                            @if($item->document)
                                <div style="display:flex;gap:6px;flex-wrap:wrap;">
                                    <a class="secondary" style="padding:6px 10px;border-radius:8px;text-decoration:none;" href="/students/{{ $application->student_id }}/documents/{{ $item->document->id }}/view">View</a>
                                    @if($item->status !== 'verified')
                                        <form method="POST" action="/students/{{ $application->student_id }}/documents/{{ $item->document->id }}/verify">
                                            @csrf
                                            <button class="secondary" type="submit">Approve</button>
                                        </form>
                                    @endif
                                    @if($item->status !== 'rejected')
                                        <form method="POST" action="/students/{{ $application->student_id }}/documents/{{ $item->document->id }}/reject" onsubmit="return promptRejectNote(this)">
                                            @csrf
                                            <input type="hidden" name="review_note" value="">
                                            <button class="secondary danger-action" type="submit">Reject</button>
                                        </form>
                                    @endif
                                </div>
                            @else
                                <span class="footer-note">Awaiting upload</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif
    </div>

    <div class="card" style="margin-top:12px;">
        <h3>Visa Case</h3>
        <form method="POST" action="/applications/{{ $application->id }}/visa">
            @csrf
            <div class="grid-4">
                <select name="visa_type">
                    <option value="" {{ !$visaCase?->visa_type ? 'selected' : '' }}>Visa Type</option>
                    <option value="student_visa" {{ $visaCase?->visa_type === 'student_visa' ? 'selected' : '' }}>Student Visa</option>
                    <option value="residence_permit" {{ $visaCase?->visa_type === 'residence_permit' ? 'selected' : '' }}>Residence Permit</option>
                    <option value="other" {{ $visaCase?->visa_type === 'other' ? 'selected' : '' }}>Other</option>
                </select>
                <div>
                    <label class="footer-note">Submission Date</label>
                    <input type="date" name="submission_date" value="{{ $visaCase?->submission_date }}" style="width:100%;">
                </div>
                <div>
                    <label class="footer-note">Embassy Appointment</label>
                    <input type="date" name="embassy_appointment_date" value="{{ $visaCase?->embassy_appointment_date }}" style="width:100%;">
                </div>
                <div>
                    <label class="footer-note">Interview Date</label>
                    <input type="date" name="interview_date" value="{{ $visaCase?->interview_date }}" style="width:100%;">
                </div>
                <select name="decision" required>
                    <option value="pending" {{ ($visaCase?->decision ?? 'pending') === 'pending' ? 'selected' : '' }}>Pending</option>
                    <option value="in_process" {{ $visaCase?->decision === 'in_process' ? 'selected' : '' }}>In Process</option>
                    <option value="approved" {{ $visaCase?->decision === 'approved' ? 'selected' : '' }}>Approved</option>
                    <option value="rejected" {{ $visaCase?->decision === 'rejected' ? 'selected' : '' }}>Rejected</option>
                </select>
                <div>
                    <label class="footer-note">Decision Date</label>
                    <input type="date" name="decision_date" value="{{ $visaCase?->decision_date }}" style="width:100%;">
                </div>
            </div>
            <textarea name="notes" rows="3" placeholder="Visa notes (embassy location, requirements, follow-up items...)" style="width:100%;margin-top:8px;">{{ $visaCase?->notes }}</textarea>
            <div style="margin-top:10px;">
                <button type="submit">Save Visa Case</button>
            </div>
        </form>
    </div>

    <div class="card" style="margin-top:12px;">
        <h3>Edit Application</h3>
            <form method="POST" action="/applications/{{ $application->id }}">
                @csrf
                @method('PUT')
                <div class="grid-4">
                    <select name="student_id" required>
                        @foreach($students as $s)
                            <option value="{{ $s->id }}" {{ $application->student_id == $s->id ? 'selected' : '' }}>{{ $s->full_name }}</option>
                        @endforeach
                    </select>
                    <select name="university_id" required>
                        @foreach($universities as $u)
                            <option value="{{ $u->id }}" {{ $application->university_id == $u->id ? 'selected' : '' }}>{{ $u->name }}</option>
                        @endforeach
                    </select>
                    <input name="program" value="{{ $application->program }}" required>
                    <select name="intake" required>
                        @if(isset($intakeTerms) && $intakeTerms->count())
                            @foreach($intakeTerms as $term)
                                <option value="{{ $term }}" {{ $application->intake === $term ? 'selected' : '' }}>{{ $term }}</option>
                            @endforeach
                            @if(!$intakeTerms->contains($application->intake))
                                <option value="{{ $application->intake }}" selected>{{ $application->intake }}</option>
                            @endif
                        @else
                            <option value="{{ $application->intake }}" selected>{{ $application->intake }}</option>
                        @endif
                    </select>
                    <select name="status">
                        @foreach(['new_lead','interested','application_started','documents_pending','interview_scheduled','offer_sent','visa_process','tuition_paid','enrolled','rejected'] as $s)
                            <option value="{{ $s }}" {{ $application->status === $s ? 'selected' : '' }}>{{ ucwords(str_replace('_', ' ', $s)) }}</option>
                        @endforeach
                    </select>
                    <input name="deadline" type="date" value="{{ $application->deadline }}">
                    <input name="next_followup_at" type="datetime-local" value="{{ $application->next_followup_at ? \Illuminate\Support\Carbon::parse($application->next_followup_at)->format('Y-m-d\TH:i') : '' }}">
                </div>
                <textarea name="notes" rows="5" style="width:100%;margin-top:8px;">{{ $application->notes }}</textarea>
                <div style="margin-top:10px;display:flex;gap:8px;">
                    <button type="submit">Save Changes</button>
                    <a class="secondary" href="/applications" style="text-decoration:none;padding:10px 14px;border-radius:10px;">Cancel</a>
                </div>
            </form>
            <form method="POST" action="/applications/{{ $application->id }}" onsubmit="return confirm('Delete application?')" style="margin-top:8px;">
                @csrf
                @method('DELETE')
                <button class="secondary" type="submit">Delete</button>
            </form>
    </div>
</div>
 </div>
<script>
function promptRejectNote(form) {
    const note = prompt('Reason for rejecting this document:');
    if (!note || !note.trim()) { return false; }
    form.querySelector('input[name="review_note"]').value = note.trim();
    return true;
}
</script>
@endsection
