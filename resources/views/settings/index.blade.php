@extends('layouts.app')

@section('content')
<div class="page-shell">
<div class="two-col">
    <div class="card">
        <h3>Profile & Preferences</h3>
        <form method="POST" action="/settings/profile">
            @csrf
            <input name="name" value="{{ $user->name }}" placeholder="Name" style="width:100%;margin-bottom:8px;">
            <select name="language" style="width:100%;margin-bottom:8px;">
                <option value="en" {{ $user->language === 'en' ? 'selected' : '' }}>English</option>
                <option value="tr" {{ $user->language === 'tr' ? 'selected' : '' }}>Turkish</option>
                <option value="fa" {{ $user->language === 'fa' ? 'selected' : '' }}>Persian</option>
            </select>
            <select name="font_scale" style="width:100%;margin-bottom:8px;">
                <option value="sm" {{ ($user->font_scale ?? 'base') === 'sm' ? 'selected' : '' }}>Small (Standard CRM)</option>
                <option value="base" {{ ($user->font_scale ?? 'base') === 'base' ? 'selected' : '' }}>Base</option>
                <option value="lg" {{ ($user->font_scale ?? 'base') === 'lg' ? 'selected' : '' }}>Large</option>
            </select>
            <div class="footer-note" style="margin:-2px 0 8px;">
                Small = compact text, Base = normal, Large = bigger text in all pages and forms.
            </div>
            <label class="footer-note" style="display:block;margin:0 0 6px;">Default Theme (applies to Admin + Student Portal)</label>
            <select name="theme" style="width:100%;margin-bottom:8px;">
                <option value="figma-light" {{ ($currentTheme ?? 'figma-light') === 'figma-light' ? 'selected' : '' }}>Figma Theme (Light)</option>
                <option value="figma-dark" {{ ($currentTheme ?? '') === 'figma-dark' ? 'selected' : '' }}>Figma Theme (Dark)</option>
                <option value="classic-light" {{ ($currentTheme ?? '') === 'classic-light' ? 'selected' : '' }}>Classic Theme (Light)</option>
                <option value="classic-dark" {{ ($currentTheme ?? '') === 'classic-dark' ? 'selected' : '' }}>Classic Theme (Dark)</option>
            </select>
            <select name="currency_preference" style="width:100%;margin-bottom:10px;">
                @foreach($currencies as $currency)
                    <option value="{{ $currency->code }}" {{ $user->currency_preference === $currency->code ? 'selected' : '' }}>
                        {{ $currency->code }}{{ $currency->symbol ? ' ('.$currency->symbol.')' : '' }}
                    </option>
                @endforeach
            </select>
            <button>Save preferences</button>
        </form>
    </div>
    <div class="card">
        <h3>Change Password</h3>
        <form method="POST" action="/settings/password">
            @csrf
            <input type="password" name="current_password" placeholder="Current password" style="width:100%;margin-bottom:8px;">
            <input type="password" name="new_password" placeholder="New password" style="width:100%;margin-bottom:8px;">
            <input type="password" name="new_password_confirmation" placeholder="Confirm new password" style="width:100%;margin-bottom:10px;">
            <button>Update password</button>
        </form>
    </div>
    @if($user->hasPermission('telegram.use'))
    <div class="card">
        <h3>Telegram</h3>
        @php($telegramLink = \App\Models\TelegramLink::query()->where('user_id', $user->id)->first())
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
            <p class="footer-note">Link your Telegram account to get notifications and quick access to students, applications and tasks.</p>
            <form method="POST" action="/telegram/generate-code">
                @csrf
                <button type="submit">Generate Link Code</button>
            </form>
        @endif
    </div>
    @endif
</div>
<div class="card" style="margin-top:12px;">
    <h3>Multi-Currency</h3>
    <form method="POST" action="/settings/currencies" class="toolbar">
        @csrf
        <input name="code" placeholder="Code (e.g. GBP)" required>
        <input name="name" placeholder="Name (e.g. British Pound)" required>
        <input name="symbol" placeholder="Symbol (e.g. £)">
        <label style="display:flex;align-items:center;gap:6px;"><input type="checkbox" name="is_default" value="1"> Default</label>
        <button type="submit">Add Currency</button>
    </form>
</div>
<div class="card" style="margin-top:12px;">
    <h3 style="display:flex;align-items:center;gap:8px;">
        WhatsApp Notifications
        <details style="display:inline-block;">
            <summary style="cursor:pointer;list-style:none;border:1px solid #cbd5e1;border-radius:999px;width:20px;height:20px;display:flex;align-items:center;justify-content:center;font-size:12px;">?</summary>
            <div class="card" style="margin-top:8px;max-width:680px;">
                <strong>WhatsApp Setup Guide</strong>
                <ol style="margin:6px 0 0 18px;line-height:1.7;">
                    <li>Enable WhatsApp feature in SaaS package/tenant features.</li>
                    <li>Get API endpoint from your provider (Meta BSP / Twilio / custom gateway).</li>
                    <li>Create API token from provider dashboard and paste in API Token.</li>
                    <li>Set sender number and enable event toggles, then Save.</li>
                </ol>
            </div>
        </details>
    </h3>
    <div class="footer-note" style="margin-bottom:8px;">
        Setup guide:
        1) In SaaS Tenant features, enable <strong>WhatsApp Notifications</strong>.
        2) Set your provider endpoint in <strong>Provider API URL</strong>.
        3) Add your Bearer token in <strong>Provider API Token</strong>.
        4) Enable the event toggles and save.
        Expected API payload: <code>{to, message, channel: "whatsapp"}</code>.
    </div>
    @if(!$whatsappFeatureEnabled)
        <p class="footer-note">This feature is disabled in your current SaaS plan.</p>
    @else
        <form method="POST" action="/settings/whatsapp">
            @csrf
            <div class="grid-4">
                <label style="display:flex;align-items:center;gap:6px;">
                    <input type="checkbox" name="whatsapp_enabled" value="1" {{ (int)($whatsappSettings->whatsapp_enabled ?? 0) === 1 ? 'checked' : '' }}>
                    Enable WhatsApp notifications
                </label>
                <input name="whatsapp_number" placeholder="WhatsApp sender number" value="{{ $whatsappSettings->whatsapp_number ?? '' }}">
                <select name="provider">
                    <option value="custom" {{ ($whatsappSettings->provider ?? 'custom') === 'custom' ? 'selected' : '' }}>Custom API</option>
                    <option value="twilio" {{ ($whatsappSettings->provider ?? '') === 'twilio' ? 'selected' : '' }}>Twilio</option>
                </select>
                <input name="api_url" placeholder="Provider API URL" value="{{ $whatsappSettings->api_url ?? '' }}">
                <input name="api_token" placeholder="Provider API Token" value="{{ $whatsappSettings->api_token ?? '' }}">
                <input type="number" min="1" max="30" name="docs_pending_days" placeholder="Docs pending threshold days" value="{{ $whatsappSettings->docs_pending_days ?? 3 }}">
            </div>
            <div style="margin-top:8px;display:flex;gap:14px;flex-wrap:wrap;">
                <label><input type="checkbox" name="notify_new_student" value="1" {{ (int)($whatsappSettings->notify_new_student ?? 0) === 1 ? 'checked' : '' }}> New student</label>
                <label><input type="checkbox" name="notify_application_update" value="1" {{ (int)($whatsappSettings->notify_application_update ?? 0) === 1 ? 'checked' : '' }}> Application updates</label>
                <label><input type="checkbox" name="notify_document_update" value="1" {{ (int)($whatsappSettings->notify_document_update ?? 0) === 1 ? 'checked' : '' }}> Document updates</label>
                <label><input type="checkbox" name="docs_pending_task_enabled" value="1" {{ (int)($whatsappSettings->docs_pending_task_enabled ?? 1) === 1 ? 'checked' : '' }}> Auto task for docs pending</label>
                <label><input type="checkbox" name="docs_pending_whatsapp_enabled" value="1" {{ (int)($whatsappSettings->docs_pending_whatsapp_enabled ?? 1) === 1 ? 'checked' : '' }}> WhatsApp on docs pending</label>
                <label><input type="checkbox" name="docs_pending_email_enabled" value="1" {{ (int)($whatsappSettings->docs_pending_email_enabled ?? 0) === 1 ? 'checked' : '' }}> Email on docs pending</label>
                <label><input type="checkbox" name="docs_pending_sms_enabled" value="1" {{ (int)($whatsappSettings->docs_pending_sms_enabled ?? 0) === 1 ? 'checked' : '' }}> SMS on docs pending</label>
            </div>
            <div style="margin-top:10px;">
                <button type="submit">Save WhatsApp settings</button>
            </div>
        </form>
    @endif
</div>
<div class="card" style="margin-top:12px;">
    <h3 style="display:flex;align-items:center;gap:8px;">
        Integration & API Settings (Email / SMS / AI)
        <details style="display:inline-block;">
            <summary style="cursor:pointer;list-style:none;border:1px solid #cbd5e1;border-radius:999px;width:20px;height:20px;display:flex;align-items:center;justify-content:center;font-size:12px;">?</summary>
            <div class="card" style="margin-top:8px;max-width:720px;">
                <strong>API Help</strong>
                <ul style="margin:6px 0 0 18px;line-height:1.7;">
                    <li>Email: set SMTP in server/.env, then add sender address/name here.</li>
                    <li>SMS: take API URL + token from your SMS provider dashboard.</li>
                    <li>AI: choose provider/model and paste API key from provider account.</li>
                </ul>
            </div>
        </details>
    </h3>
    <form method="POST" action="/settings/integrations">
        @csrf
        <div class="grid-4">
            <label><input type="checkbox" name="email_enabled" value="1" {{ (int)($integrationSettings->email_enabled ?? 0) === 1 ? 'checked' : '' }}> Enable Email Automation</label>
            <input type="email" name="email_from_address" placeholder="Email From Address" value="{{ $integrationSettings->email_from_address ?? '' }}">
            <input type="text" name="email_from_name" placeholder="Email From Name" value="{{ $integrationSettings->email_from_name ?? '' }}">
            <label><input type="checkbox" name="sms_enabled" value="1" {{ (int)($integrationSettings->sms_enabled ?? 0) === 1 ? 'checked' : '' }}> Enable SMS Automation</label>
            <input type="url" name="sms_api_url" placeholder="SMS API URL" value="{{ $integrationSettings->sms_api_url ?? '' }}">
            <input type="text" name="sms_api_token" placeholder="SMS API Token" value="{{ $integrationSettings->sms_api_token ?? '' }}">
            <label><input type="checkbox" name="ai_enabled" value="1" {{ (int)($integrationSettings->ai_enabled ?? 0) === 1 ? 'checked' : '' }}> Enable AI Layer</label>
            <select name="ai_provider">
                <option value="openai" {{ ($integrationSettings->ai_provider ?? 'openai') === 'openai' ? 'selected' : '' }}>OpenAI</option>
                <option value="custom" {{ ($integrationSettings->ai_provider ?? '') === 'custom' ? 'selected' : '' }}>Custom</option>
            </select>
            <input type="text" name="ai_model" placeholder="AI model (e.g. gpt-4o-mini)" value="{{ $integrationSettings->ai_model ?? '' }}">
            <input type="text" name="ai_api_key" placeholder="AI API key" value="{{ $integrationSettings->ai_api_key ?? '' }}">
        </div>
        <div style="margin-top:10px;">
            <button type="submit">Save Integration Settings</button>
        </div>
    </form>
</div>
<div class="card" style="margin-top:12px;">
    <h3>Lead Source Costs (for CAC / Source ROI)</h3>
    <form method="POST" action="/settings/lead-source-costs" class="toolbar">
        @csrf
        <select name="source_key" required>
            @foreach(['website_form','landing_page','meta_ads','google_ads','whatsapp','instagram','telegram','email','phone_call','education_fair','referral','walk_in','other'] as $src)
                <option value="{{ $src }}">{{ ucwords(str_replace('_',' ', $src)) }}</option>
            @endforeach
        </select>
        <input type="number" step="0.01" min="0" name="monthly_cost" placeholder="Monthly cost" required>
        <button type="submit">Save Cost</button>
    </form>
    <table class="table-compact" style="margin-top:10px;">
        <thead><tr><th>Source</th><th>Monthly Cost</th></tr></thead>
        <tbody>
        @forelse($sourceCosts as $cost)
            <tr>
                <td>{{ ucwords(str_replace('_',' ', $cost->source_key)) }}</td>
                <td>{{ number_format((float) $cost->monthly_cost, 2) }}</td>
            </tr>
        @empty
            <tr><td colspan="2">No source costs defined yet.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
 </div>
@endsection
