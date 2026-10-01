@extends('layouts.app')

@section('content')
<div class="card">
    <div class="toolbar">
        <h2 style="margin:0;">Notification Center</h2>
        <form method="GET" action="/notifications" class="toolbar" style="margin:0;">
            <select name="per_page" class="per-page-select" title="Items per page" aria-label="Items per page" onchange="this.form.submit()">
                @foreach([15, 50, 100] as $size)
                    <option value="{{ $size }}" {{ (int)($perPage ?? 50) === $size ? 'selected' : '' }}>{{ $size }}</option>
                @endforeach
            </select>
        </form>
        <form method="POST" action="/notifications/read-all">
            @csrf
            <button class="secondary" type="submit">Mark all as read</button>
        </form>
    </div>
    @forelse($notifications as $n)
        @php
            $meta = json_decode((string) $n->meta_json, true) ?: [];
            $target = null;
            if (!empty($meta['student_request_id'])) {
                $target = '/student-requests/'.$meta['student_request_id'];
            } elseif (!empty($meta['student_id'])) {
                $target = '/students/'.$meta['student_id'];
            } elseif (!empty($meta['message_id'])) {
                $target = '/messages'.(!empty($meta['student_id']) ? '?student_id='.$meta['student_id'] : '');
            }
        @endphp
        <div class="notif-item {{ $n->read_at ? '' : 'unread' }}">
            <div style="display:flex;justify-content:space-between;gap:8px;">
                <strong>{{ $n->title }}</strong>
                <span class="footer-note">{{ $n->created_at }}</span>
            </div>
            <div>{{ $n->body }}</div>
            @if($target)
                <div style="margin-top:8px;"><a class="tab" href="{{ $target }}">Open</a></div>
            @endif
            @if(!$n->read_at)
                <form method="POST" action="/notifications/{{ $n->id }}/read" style="margin-top:8px;">
                    @csrf
                    <button class="secondary">Mark read</button>
                </form>
            @endif
        </div>
    @empty
        <p class="footer-note">No notifications found.</p>
    @endforelse

    <div class="pagination-wrap">{{ $notifications->links() }}</div>
</div>
@endsection
