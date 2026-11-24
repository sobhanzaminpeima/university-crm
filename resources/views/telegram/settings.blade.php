@extends('layouts.app')

@section('content')
@php
    $code = session('telegram_code');
    $deepLink = ($code && $botUsername) ? 'https://t.me/'.$botUsername.'?start=link_'.$code : null;
@endphp
<div class="page-shell tg-page">
    <div class="card">
        <div class="tg-head">
            <div>
                <h3 style="margin:0;">Your Telegram connection</h3>
                <p class="footer-note" style="margin:4px 0 0;">Get CRM notifications on your phone and use quick commands (students, applications, tasks, messages).</p>
            </div>
            @if($telegramLink)
                <span class="tg-pill tg-ok">● Connected</span>
            @else
                <span class="tg-pill tg-off">○ Not connected</span>
            @endif
        </div>

        @if($telegramLink)
            <p style="margin:14px 0;">
                Connected to <strong>{{ $telegramLink->telegram_username ? '@'.$telegramLink->telegram_username : 'a Telegram account' }}</strong>
                since {{ \Illuminate\Support\Carbon::parse($telegramLink->linked_at)->format('Y-m-d') }}.
            </p>
            <div class="toolbar">
                <form method="POST" action="/telegram/test-message">@csrf<button type="submit">Send me a test message</button></form>
                <form method="POST" action="/telegram/unlink" onsubmit="return confirm('Disconnect Telegram? You will stop receiving CRM notifications there.')">@csrf<button class="secondary" type="submit">Disconnect</button></form>
            </div>
        @elseif($code)
            <ol class="tg-steps">
                @if($deepLink)
                    <li>
                        <strong>Tap the button</strong> — Telegram opens the bot and connects you automatically.
                        <div style="margin-top:8px;"><a class="tg-btn" href="{{ $deepLink }}" target="_blank" rel="noopener">Open Telegram &amp; connect</a></div>
                    </li>
                    <li>Or, on another device, send this message to <strong>{{ '@'.$botUsername }}</strong>:</li>
                @else
                    <li>Open the CRM bot in Telegram and send this message:</li>
                @endif
            </ol>
            <div class="tg-code" aria-live="polite">
                <code id="tgLinkCode" dir="ltr">/link {{ $code }}</code>
                <button type="button" class="secondary" onclick="navigator.clipboard && navigator.clipboard.writeText(document.getElementById('tgLinkCode').textContent).then(() => { this.textContent = 'Copied ✓'; })">Copy</button>
            </div>
            <p class="footer-note">The code works once and expires in 10 minutes. After connecting, reload this page.</p>
            <form method="POST" action="/telegram/generate-code" style="margin-top:8px;">@csrf<button class="secondary" type="submit">Generate a new code</button></form>
        @else
            <ol class="tg-steps">
                <li>Click <strong>Connect Telegram</strong> below.</li>
                <li>Tap <strong>Open Telegram</strong> (or send the code to the bot).</li>
                <li>Done — notifications will arrive in Telegram.</li>
            </ol>
            <form method="POST" action="/telegram/generate-code">@csrf<button type="submit">Connect Telegram</button></form>
            @if(!$botUsername)
                <p class="footer-note" style="margin-top:8px;">The bot is not fully set up yet. Ask your administrator if connecting does not work.</p>
            @endif
        @endif
    </div>

    @if($setup)
        <div class="card" style="margin-top:14px;">
            <div class="tg-head">
                <div>
                    <h3 style="margin:0;">Bot setup <span class="footer-note">(platform administrator)</span></h3>
                    <p class="footer-note" style="margin:4px 0 0;">One bot serves all agencies. Complete the checklist, then press <em>Connect webhook</em>.</p>
                </div>
                @if($setup['webhook_connected'])
                    <span class="tg-pill tg-ok">● Bot online</span>
                @else
                    <span class="tg-pill tg-warn">▲ Setup needed</span>
                @endif
            </div>

            <ul class="tg-checklist">
                <li class="{{ $setup['token_valid'] ? 'ok' : 'bad' }}">
                    <strong>Bot token</strong>
                    @if($setup['token_valid'])
                        — valid: <strong>{{ $setup['bot_name'] }}</strong> (<a href="https://t.me/{{ $setup['bot_username'] }}" target="_blank" rel="noopener">{{ '@'.$setup['bot_username'] }}</a>)
                    @elseif($setup['token'])
                        — Telegram rejected the token{{ $setup['token_error'] ? ': '.$setup['token_error'] : '' }}. Check <code>TELEGRAM_BOT_TOKEN</code> in <code>.env</code>.
                    @else
                        — missing. In Telegram open <a href="https://t.me/BotFather" target="_blank" rel="noopener">@BotFather</a>, send <code>/newbot</code>, then put the token in <code>.env</code> as <code>TELEGRAM_BOT_TOKEN=…</code>.
                    @endif
                </li>
                <li class="{{ $setup['secret_ok'] ? 'ok' : 'bad' }}">
                    <strong>Webhook secret</strong>
                    @if($setup['secret_ok'])
                        — set.
                    @else
                        — set <code>TELEGRAM_WEBHOOK_SECRET</code> in <code>.env</code> to a random value (24+ characters). Suggested:
                        <div class="tg-code" style="margin-top:6px;"><code dir="ltr" id="tgSecret">{{ $setup['suggested_secret'] }}</code><button type="button" class="secondary" onclick="navigator.clipboard && navigator.clipboard.writeText(document.getElementById('tgSecret').textContent).then(() => { this.textContent = 'Copied ✓'; })">Copy</button></div>
                    @endif
                </li>
                <li class="{{ $setup['https'] ? 'ok' : 'bad' }}">
                    <strong>HTTPS address</strong> — <code dir="ltr">{{ $setup['app_url'] ?: 'APP_URL not set' }}</code>
                    @unless($setup['https']) <span>(Telegram requires <code>https://</code> in <code>APP_URL</code>)</span> @endunless
                </li>
                <li class="{{ $setup['header_secret'] ? 'ok' : 'info' }}">
                    <strong>Extra header check</strong> — {{ $setup['header_secret'] ? 'enabled.' : 'optional: set TELEGRAM_WEBHOOK_HEADER_SECRET for defence in depth.' }}
                </li>
                <li class="{{ $setup['webhook_connected'] ? 'ok' : 'bad' }}">
                    <strong>Webhook</strong>
                    @if($setup['webhook_connected'])
                        — connected. Pending updates: {{ $setup['pending_updates'] }}.
                    @elseif($setup['webhook_other'])
                        — pointing to a different address. Press <em>Connect webhook</em> to fix it.
                    @else
                        — not connected.
                    @endif
                    @if($setup['last_error'])
                        <div class="field-error">Last delivery error ({{ $setup['last_error_at'] }}): {{ $setup['last_error'] }}</div>
                    @endif
                </li>
            </ul>
            <p class="footer-note">After editing <code>.env</code> on the server, run <code>php artisan config:clear</code> (or <code>config:cache</code>) so the new values are loaded, then reload this page.</p>

            <div class="toolbar" style="margin-top:10px;">
                <form method="POST" action="/telegram/setup/webhook">@csrf<button type="submit" @disabled(!$setup['token_valid'] || !$setup['secret_ok'] || !$setup['https'])>{{ $setup['webhook_connected'] ? 'Reconnect webhook' : 'Connect webhook' }}</button></form>
                <form method="POST" action="/telegram/setup/commands">@csrf<button type="submit" class="secondary" @disabled(!$setup['token_valid'])>Register command menu</button></form>
                <a class="tab" href="/telegram">Re-check</a>
                @if($setup['webhook_connected'] || $setup['webhook_other'])
                    <form method="POST" action="/telegram/setup/webhook/delete" onsubmit="return confirm('Disconnect the webhook? The bot will stop answering for every agency.')">@csrf<button type="submit" class="secondary">Disconnect webhook</button></form>
                @endif
            </div>
        </div>
    @endif
</div>
@endsection
