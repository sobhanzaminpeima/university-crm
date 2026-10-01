@extends('layouts.portal')

@section('content')
<div class="card">
    <h2 style="margin-top:0;">Welcome, {{ $student->full_name }}</h2>
    <p><strong>Email:</strong> {{ $student->email }}</p>
    <p><strong>Current Stage:</strong> <span class="badge {{ $student->stage }}">{{ ucfirst($student->stage) }}</span></p>
    <div class="tabs">
        <a class="tab" href="/portal/universities">Universities</a>
        <a class="tab" href="/portal/applications">Applications</a>
        <a class="tab" href="/portal/documents">Documents</a>
        <a class="tab" href="/portal/messages">Messages</a>
    </div>
    <div class="grid-4">
        <div class="card"><h3>Applications</h3><div class="metric">{{ $applications->count() }}</div></div>
        <div class="card"><h3>Documents</h3><div class="metric">{{ $uploadedCount }}/{{ $requiredCount }}</div></div>
        <div class="card"><h3>Messages</h3><div class="metric">{{ $messages->count() }}</div></div>
        <div class="card"><h3>Profile Status</h3><div class="metric">{{ (int) $student->is_active === 1 ? 'Active' : 'Inactive' }}</div></div>
    </div>
    <div class="card" style="margin-top:12px;">
        <h3>Telegram</h3>
        @php($telegramLink = \App\Models\TelegramLink::query()->where('user_id', $student->user_id)->first())
        @if(session('telegram_code'))
            <div class="card" style="border-color:#0ea5e9;background:#f0f9ff;margin-bottom:10px;">
                <p style="margin:0 0 6px;">Send this to the bot on Telegram:</p>
                <p style="font-size:20px;font-weight:800;letter-spacing:.08em;margin:0;">/link {{ session('telegram_code') }}</p>
                <p class="footer-note" style="margin:6px 0 0;">Expires in 10 minutes.</p>
            </div>
        @endif
        @if($telegramLink)
            <p class="footer-note">Linked to {{ $telegramLink->telegram_username ? '@'.$telegramLink->telegram_username : 'a Telegram account' }}.</p>
            <form method="POST" action="/portal/telegram/unlink">
                @csrf
                <button class="secondary" type="submit">Unlink Telegram</button>
            </form>
        @else
            <p class="footer-note">Link Telegram to get updates on your applications and documents.</p>
            <form method="POST" action="/portal/telegram/generate-code">
                @csrf
                <button type="submit">Generate Link Code</button>
            </form>
        @endif
    </div>
    <div class="card" style="margin-top:12px;">
        <h3>Application Progress</h3>
        <div style="height:10px;background:#dbeafe;border-radius:999px;overflow:hidden;">
            <div style="height:10px;width:{{ $documentProgress }}%;background:#0284c7;"></div>
        </div>
        <p class="footer-note">{{ $documentProgress }}% profile completion based on required documents.</p>
    </div>
    <div class="two-col" style="margin-top:12px;">
        <div class="card">
            <h3>Status Timeline</h3>
            @php
                $steps = ['lead' => 'Lead', 'applied' => 'Applied', 'offered' => 'Offered', 'accepted' => 'Accepted', 'enrolled' => 'Enrolled'];
                $order = array_keys($steps);
                $currentIndex = array_search($student->stage, $order, true);
                if ($currentIndex === false) { $currentIndex = 0; }
            @endphp
            @foreach($steps as $key => $label)
                @php $idx = array_search($key, $order, true); @endphp
                <div class="stat-row">
                    <span>{{ $label }}</span>
                    <strong>{{ $idx <= $currentIndex ? 'Done' : 'Pending' }}</strong>
                </div>
            @endforeach
        </div>
        <div class="card">
            <h3>Documents Checklist</h3>
            @foreach($documents as $doc)
                <div class="stat-row">
                    <span>{{ $doc->label }}</span>
                    <strong>{{ $doc->is_missing ? 'Missing' : ucfirst($doc->status) }}</strong>
                </div>
            @endforeach
        </div>
    </div>
</div>
@endsection
