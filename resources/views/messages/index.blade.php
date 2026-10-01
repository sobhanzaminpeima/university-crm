@extends('layouts.app')

@section('content')
<div class="page-shell">
<div class="two-col">
    <div class="card">
        <h2 style="margin-top:0;">Student Conversations</h2>
        <table class="table-compact">
            <thead><tr><th>Student</th><th>Open</th></tr></thead>
            <tbody>
            @forelse($students as $student)
                <tr>
                    <td>{{ $student->full_name }}</td>
                    <td><a class="tab" href="/messages?student_id={{ $student->id }}">Open</a></td>
                </tr>
            @empty
                <tr><td colspan="2">No students found.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div class="card">
        <h2 style="margin-top:0;">Thread</h2>
        @if($selectedStudent)
            <p><strong>Student:</strong> {{ $selectedStudent->full_name }}</p>
            <form method="POST" action="/messages" enctype="multipart/form-data">
                @csrf
                <input type="hidden" name="student_id" value="{{ $selectedStudent->id }}">
                <textarea name="body" rows="4" style="width:100%;" placeholder="Write a message..." required></textarea>
                <input type="file" name="attachment" style="margin-top:8px;">
                <button type="submit" style="margin-top:8px;">Send</button>
            </form>
            <div style="margin-top:12px;">
                @forelse($messages as $message)
                    <div class="notif-item {{ $message->sender_role === 'student' ? 'unread' : '' }}">
                        <div style="display:flex;justify-content:space-between;gap:8px;">
                            <strong>{{ ucfirst($message->sender_role) }}</strong>
                            <span class="footer-note">{{ $message->created_at }}</span>
                        </div>
                        <div>{{ $message->body }}</div>
                        @if(!empty($message->attachment_url))
                            <div style="margin-top:6px;"><a class="tab" href="{{ $message->attachment_url }}" target="_blank">Attachment: {{ $message->attachment_name ?: 'download' }}</a></div>
                        @endif
                    </div>
                @empty
                    <p class="footer-note">No messages in this thread.</p>
                @endforelse
            </div>
            <div style="margin-top:10px;">{{ $messages->links() }}</div>
        @else
            <p class="footer-note">Select a student to open conversation.</p>
        @endif
    </div>
</div>
 </div>
@endsection
