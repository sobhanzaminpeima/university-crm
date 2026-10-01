@extends('layouts.app')

@php
    $tab = request()->query('tab', 'overview');
@endphp

@section('content')
<div class="page-shell">
<div class="card">
    <h2 style="margin-top:0;">{{ $student->full_name }}</h2>
    <div class="tabs">
        <a class="tab {{ $tab === 'overview' ? 'active' : '' }}" href="/students/{{ $student->id }}?tab=overview">Overview</a>
        <a class="tab {{ $tab === 'apps' ? 'active' : '' }}" href="/students/{{ $student->id }}?tab=apps">Apps ({{ $apps->count() }})</a>
        <a class="tab {{ $tab === 'docs' ? 'active' : '' }}" href="/students/{{ $student->id }}?tab=docs">Docs ({{ $docs->count() }})</a>
        <a class="tab {{ $tab === 'messages' ? 'active' : '' }}" href="/students/{{ $student->id }}?tab=messages">Messages ({{ $messages->count() }})</a>
        <a class="tab {{ $tab === 'tasks' ? 'active' : '' }}" href="/students/{{ $student->id }}?tab=tasks">Tasks ({{ $tasks->count() }})</a>
        <a class="tab {{ $tab === 'timeline' ? 'active' : '' }}" href="/students/{{ $student->id }}?tab=timeline">Timeline</a>
    </div>

    @if($tab === 'overview')
    <div class="two-col">
        <div class="card">
            <h3>Academic Profile</h3>
            <p><strong>Email:</strong> {{ $student->email }}</p>
            <p><strong>Phone:</strong> {{ $student->phone ?: '-' }}</p>
            <p><strong>GPA:</strong> {{ $student->gpa ?: '-' }}</p>
            <p><strong>English Level:</strong> {{ $student->english_level ?: '-' }}</p>
            <p><strong>Field of Study:</strong> {{ $student->field_of_study ?: '-' }}</p>
            <p><strong>Lead Source:</strong> {{ $student->lead_source ? ucwords(str_replace('_',' ', $student->lead_source)) : '-' }}</p>
            <p><strong>Target Country:</strong> {{ $student->target_country ?: '-' }}</p>
            <p><strong>Budget (USD):</strong> {{ $student->budget_usd ?: '-' }}</p>
            <p><strong>Passport #:</strong> {{ $student->passport_number ?: '-' }}</p>
            <p><strong>Source Agent:</strong> {{ $student->agent?->name ?: '-' }}</p>
            <p><strong>Source Sub-Agent:</strong> {{ $student->subAgent?->name ?: '-' }}</p>
        </div>
        <div class="card">
            <h3>Case Health</h3>
            <p><strong>Current Stage:</strong> <span class="badge {{ $student->stage }}">{{ ucwords(str_replace('_', ' ', $student->stage)) }}</span></p>
            <p><strong>Lifecycle:</strong> {{ ucwords(str_replace('_', ' ', (string) ($student->lifecycle_stage ?? 'lead'))) }}</p>
            <p><strong>Temperature:</strong> <span class="{{ $student->stage_temperature }}">{{ ucfirst($student->stage_temperature ?: 'cold') }}</span></p>
            <p><strong>Applications:</strong> {{ $apps->count() }}</p>
            <p><strong>Documents:</strong> {{ $docs->count() }}</p>
            <p><strong>Open Tasks:</strong> {{ $tasks->whereIn('status', ['todo', 'in_progress'])->count() }}</p>
        </div>
    </div>
    <div class="card" style="margin-top:10px;">
        <h3>AI & Smart Matching</h3>
        <div class="grid-4">
            <div><strong>Lead Score:</strong> {{ $aiInsights['lead_score'] }}/100</div>
            <div><strong>Enroll Probability:</strong> {{ $aiInsights['enroll_probability'] }}%</div>
            <div><strong>Risk Score:</strong> {{ $aiInsights['risk_score'] }}/100</div>
        </div>
        <p class="footer-note" style="margin-top:8px;">{{ $aiInsights['summary'] }}</p>
        <h4 style="margin-bottom:6px;">Recommended Universities</h4>
        <ul style="margin:0;padding-left:18px;">
            @forelse($aiInsights['recommended_universities'] as $rec)
                <li>{{ $rec->name }} - Match {{ $rec->match_score }}%</li>
            @empty
                <li>No recommendation yet.</li>
            @endforelse
        </ul>
    </div>
    @elseif($tab === 'apps')
    <table class="table-compact">
        <thead><tr><th>ID</th><th>Program</th><th>Status</th><th>Intake</th><th>Action</th></tr></thead>
        <tbody>
            @forelse($apps as $app)
                <tr>
                    <td>#{{ $app->id }}</td>
                    <td>{{ $app->program }}</td>
                    <td><span class="badge {{ $app->status }}">{{ ucfirst($app->status) }}</span></td>
                    <td>{{ $app->intake }}</td>
                    <td><a class="tab" href="/applications/{{ $app->id }}">Open</a></td>
                </tr>
            @empty
                <tr><td colspan="5">No applications for this student yet.</td></tr>
            @endforelse
        </tbody>
    </table>
    @elseif($tab === 'docs')
    <table class="table-compact">
        <thead><tr><th>Type</th><th>Status</th><th>File</th><th>Action</th></tr></thead>
        <tbody>
            @forelse($requiredDocs as $doc)
                <tr>
                    <td>{{ $doc->label }}</td>
                    <td>
                        @if($doc->is_missing)
                            <span class="badge rejected">Missing</span>
                        @elseif($doc->status === 'verified')
                            <span class="badge enrolled">Verified</span>
                        @else
                            <span class="badge applied">Uploaded</span>
                        @endif
                    </td>
                    <td>
                        @if($doc->file_url)
                            <a href="/students/{{ $student->id }}/documents/{{ $doc->id }}/view" target="_blank">Preview</a>
                            <span class="footer-note">|</span>
                            <a href="/students/{{ $student->id }}/documents/{{ $doc->id }}/view?download=1" target="_blank">Download</a>
                        @else
                            -
                        @endif
                    </td>
                    <td style="display:flex;gap:6px;">
                        <form method="POST" action="/students/{{ $student->id }}/documents" enctype="multipart/form-data" style="display:flex;gap:6px;align-items:center;" onsubmit="this.querySelector('button[type=submit]').disabled=true;this.querySelector('button[type=submit]').innerText='Uploading...';">
                            @csrf
                            <input type="hidden" name="type" value="{{ $doc->type }}">
                            <input type="file" name="file" required style="max-width:190px;">
                            <button type="submit" class="secondary">{{ $doc->is_missing ? 'Upload' : 'Replace' }}</button>
                        </form>
                        @if(!$doc->is_missing && $doc->status !== 'verified')
                            <form method="POST" action="/students/{{ $student->id }}/documents/{{ $doc->id }}/verify">
                                @csrf
                                <button type="submit">Verify</button>
                            </form>
                        @endif
                        @if(!$doc->is_missing)
                            <form method="POST" action="/students/{{ $student->id }}/documents/{{ $doc->id }}" onsubmit="return confirm('Delete this document?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="secondary">Delete</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="4">No documents found.</td></tr>
            @endforelse
        </tbody>
    </table>
    <div class="card" style="margin-top:10px;">
        <h3>Offer / Acceptance Letters</h3>
        <form method="POST" action="/students/{{ $student->id }}/documents" enctype="multipart/form-data" style="display:grid;grid-template-columns:2fr 1fr 1fr 1fr auto;gap:8px;align-items:center;" onsubmit="this.querySelector('button[type=submit]').disabled=true;this.querySelector('button[type=submit]').innerText='Uploading...';">
            @csrf
            <input type="hidden" name="type" value="acceptance_letter">
            <input type="text" name="letter_title" placeholder="Letter title (Initial/Final...)" value="{{ old('letter_title') }}">
            <input type="date" name="letter_date" value="{{ old('letter_date') }}">
            <input type="file" name="file" accept=".pdf,.jpg,.jpeg,.png,.webp" required>
            <button type="submit">Send to Student</button>
        </form>
        <table class="table-compact" style="margin-top:10px;">
            <thead><tr><th>Title</th><th>Date</th><th>File</th></tr></thead>
            <tbody>
            @forelse($offerLetters as $letter)
                @php($meta = json_decode((string) $letter->ocr_json, true) ?: [])
                <tr>
                    <td>{{ $meta['title'] ?? 'Acceptance Letter' }}</td>
                    <td>{{ $meta['letter_date'] ?? '-' }}</td>
                    <td>
                        <a href="/students/{{ $student->id }}/documents/{{ $letter->id }}/view" target="_blank">Preview</a>
                        <span class="footer-note">|</span>
                        <a href="/students/{{ $student->id }}/documents/{{ $letter->id }}/view?download=1" target="_blank">Download</a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="3">No acceptance letters sent yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @elseif($tab === 'messages')
    <div class="card" style="padding:12px;max-height:480px;overflow-y:auto;display:flex;flex-direction:column;gap:8px;">
        @forelse($messages->reverse() as $msg)
            @php($fromStudent = $msg->sender_role === 'student')
            <div style="display:flex;{{ $fromStudent ? '' : 'justify-content:flex-end;' }}">
                <div style="max-width:70%;padding:10px 12px;border-radius:12px;background:{{ $fromStudent ? 'var(--bg)' : 'var(--primary-soft)' }};border:1px solid var(--border);">
                    <div class="footer-note" style="margin-bottom:4px;">{{ $fromStudent ? 'Student' : 'CRM' }} · {{ $msg->created_at }}</div>
                    <div>{{ $msg->body }}</div>
                    @if($msg->attachment_url)
                        <div style="margin-top:6px;"><a href="{{ $msg->attachment_url }}" target="_blank">📎 {{ $msg->attachment_name ?: 'Open file' }}</a></div>
                    @endif
                </div>
            </div>
        @empty
            <p class="footer-note">No messages yet. Start the conversation below.</p>
        @endforelse
    </div>
    <form method="POST" action="/messages" enctype="multipart/form-data" style="margin-top:10px;">
        @csrf
        <input type="hidden" name="student_id" value="{{ $student->id }}">
        <textarea name="body" rows="3" placeholder="Type a message to the student..." required style="width:100%;"></textarea>
        <div class="toolbar" style="margin-top:8px;">
            <input type="file" name="attachment">
            <button type="submit">Send</button>
        </div>
    </form>
    @elseif($tab === 'tasks')
    <table class="table-compact">
        <thead><tr><th>Title</th><th>Priority</th><th>Status</th><th>Deadline</th></tr></thead>
        <tbody>
            @forelse($tasks as $task)
                <tr>
                    <td>{{ $task->title }}</td>
                    <td>{{ ucfirst($task->priority) }}</td>
                    <td>{{ ucfirst($task->status) }}</td>
                    <td>{{ $task->deadline ?: '-' }}</td>
                </tr>
            @empty
                <tr><td colspan="4">No tasks assigned.</td></tr>
            @endforelse
        </tbody>
    </table>
    @else
    <div class="card">
        <h3>Timeline</h3>
        <div style="display:flex;flex-direction:column;gap:10px;margin-top:10px;">
            @forelse($timeline as $event)
                <div style="display:flex;gap:10px;padding-bottom:10px;border-bottom:1px dashed var(--border);">
                    <div style="font-size:18px;">{{ $event['icon'] }}</div>
                    <div>
                        <div><strong>{{ $event['title'] }}</strong></div>
                        @if($event['body'])
                            <div class="footer-note">{{ $event['body'] }}</div>
                        @endif
                        <div class="footer-note">{{ \Illuminate\Support\Carbon::parse($event['at'])->format('Y-m-d H:i') }}</div>
                    </div>
                </div>
            @empty
                <p class="footer-note">No activity recorded yet.</p>
            @endforelse
        </div>
    </div>
    @endif
</div>
 </div>
@endsection
