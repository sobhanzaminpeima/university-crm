@extends('layouts.app')

@section('content')
<div class="card" style="max-width:760px;">
    <h3>Telegram Bot</h3>
    @if(session('telegram_code'))
        <div class="card" style="border-color:#0ea5e9;background:#f0f9ff;margin-bottom:10px;">
            <p style="margin:0 0 6px;">Send this to the bot on Telegram:</p>
            <p style="font-size:20px;font-weight:800;letter-spacing:.08em;margin:0;">/link {{ session('telegram_code') }}</p>
            <p class="footer-note" style="margin:6px 0 0;">Expires in 10 minutes.</p>
        </div>
    @endif

    @if($telegramLink)
        <p class="footer-note">Linked to {{ $telegramLink->telegram_username ? '@'.$telegramLink->telegram_username : 'a Telegram account' }} on {{ \Illuminate\Support\Carbon::parse($telegramLink->linked_at)->format('Y-m-d') }}.</p>
        <form method="POST" action="/telegram/unlink">
            @csrf
            <button class="secondary" type="submit">Unlink Telegram</button>
        </form>
    @else
        <p class="footer-note">Link your Telegram account to receive notifications and use the CRM bot features allowed by your role.</p>
        <form method="POST" action="/telegram/generate-code">
            @csrf
            <button type="submit">Generate Link Code</button>
        </form>
    @endif
</div>
@endsection
