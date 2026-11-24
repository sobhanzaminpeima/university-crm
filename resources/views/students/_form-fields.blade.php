{{--
    Shared student form fields with visible labels.
    Expects: $student (nullable model), $formId (unique prefix), $agents, $subAgents, $studyFields.
    Old input is only re-applied to the form that was actually submitted (matched via _modal).
--}}
@php
    $useOld = old('_modal') === $formId;
    $val = function (string $field, $default = '') use ($student, $useOld) {
        if ($useOld) {
            return old($field, $default);
        }
        return $student ? ($student->{$field} ?? $default) : $default;
    };
    $isNew = $student === null;
    $stages = ['lead','inquiry','applicant','documents_pending','interview_scheduled','admitted','visa_process','tuition_paid','enrolled','alumni'];
    $lifecycles = ['lead','inquiry','applicant','admitted','enrolled','alumni'];
    $sources = ['website_form','landing_page','meta_ads','google_ads','whatsapp','instagram','telegram','email','phone_call','education_fair','referral','walk_in','other'];
    $human = fn ($v) => ucfirst(str_replace('_', ' ', (string) $v));
    $err = fn (string $field) => $useOld && $errors->has($field) ? $errors->first($field) : null;
@endphp

@if($useOld && $errors->any())
    <div class="form-error-summary" role="alert">
        <strong>Nothing was saved yet. Please check the highlighted fields.</strong>
        <ul>
            @foreach($errors->all() as $message)
                <li>{{ $message }}</li>
            @endforeach
        </ul>
    </div>
@endif

<fieldset class="form-section">
    <legend>Contact &amp; portal access</legend>
    <div class="grid-4">
        <label class="field">
            <span>Full name <abbr title="required">*</abbr></span>
            <input name="full_name" value="{{ $val('full_name') }}" required autocomplete="off" @if($err('full_name')) aria-invalid="true" @endif>
            @if($err('full_name'))<small class="field-error">{{ $err('full_name') }}</small>@endif
        </label>
        <label class="field">
            <span>Email <abbr title="required">*</abbr></span>
            <input name="email" type="email" dir="ltr" value="{{ $val('email') }}" required autocomplete="off" @if($err('email')) aria-invalid="true" @endif>
            @if($err('email'))<small class="field-error">{{ $err('email') }}</small>@endif
        </label>
        <label class="field">
            <span>Phone</span>
            <input name="phone" type="tel" dir="ltr" value="{{ $val('phone') }}" autocomplete="off" @if($err('phone')) aria-invalid="true" @endif>
            @if($err('phone'))<small class="field-error">{{ $err('phone') }}</small>@endif
        </label>
        <label class="field">
            <span>{{ $isNew ? 'Portal password' : 'New portal password' }} @if($isNew)<abbr title="required">*</abbr>@endif</span>
            <input name="account_password" type="password" minlength="8" autocomplete="new-password" @if($isNew) required @endif
                   placeholder="{{ $isNew ? 'At least 8 characters' : 'Leave blank to keep current' }}" @if($err('account_password')) aria-invalid="true" @endif>
            @if($err('account_password'))<small class="field-error">{{ $err('account_password') }}</small>@endif
        </label>
        <label class="field">
            <span>Portal login</span>
            <select name="is_active">
                <option value="1" {{ (string) $val('is_active', '1') === '1' ? 'selected' : '' }}>Active</option>
                <option value="0" {{ (string) $val('is_active', '1') === '0' ? 'selected' : '' }}>Inactive</option>
            </select>
        </label>
    </div>
</fieldset>

<fieldset class="form-section">
    <legend>Pipeline &amp; assignment</legend>
    <div class="grid-4">
        <label class="field">
            <span>Stage <abbr title="required">*</abbr></span>
            <select name="stage" required>
                @foreach($stages as $st)
                    <option value="{{ $st }}" {{ $val('stage', 'lead') === $st ? 'selected' : '' }}>{{ $human($st) }}</option>
                @endforeach
            </select>
        </label>
        <label class="field">
            <span>Lifecycle stage</span>
            <select name="lifecycle_stage">
                @foreach($lifecycles as $lc)
                    <option value="{{ $lc }}" {{ $val('lifecycle_stage', 'lead') === $lc ? 'selected' : '' }}>{{ $human($lc) }}</option>
                @endforeach
            </select>
        </label>
        <label class="field">
            <span>Lead source</span>
            <select name="lead_source">
                <option value="">— Not set —</option>
                @foreach($sources as $src)
                    <option value="{{ $src }}" {{ $val('lead_source') === $src ? 'selected' : '' }}>{{ ucwords(str_replace('_', ' ', $src)) }}</option>
                @endforeach
            </select>
        </label>
        <label class="field">
            <span>Agent</span>
            <select name="agent_id">
                <option value="">— Unassigned —</option>
                @foreach($agents as $agent)
                    <option value="{{ $agent->id }}" {{ (string) $val('agent_id') === (string) $agent->id ? 'selected' : '' }}>{{ $agent->name }}</option>
                @endforeach
            </select>
        </label>
        <label class="field">
            <span>Sub-agent</span>
            <select name="sub_agent_id">
                <option value="">— Unassigned —</option>
                @foreach($subAgents as $sub)
                    <option value="{{ $sub->id }}" {{ (string) $val('sub_agent_id') === (string) $sub->id ? 'selected' : '' }}>{{ $sub->name }}</option>
                @endforeach
            </select>
        </label>
    </div>
</fieldset>

<fieldset class="form-section">
    <legend>Academic profile &amp; preferences</legend>
    <div class="grid-4">
        <label class="field">
            <span>Nationality</span>
            <input name="nationality" value="{{ $val('nationality') }}">
        </label>
        <label class="field">
            <span>Target country</span>
            <input name="target_country" value="{{ $val('target_country') }}">
        </label>
        <label class="field">
            <span>GPA (0–4)</span>
            <input name="gpa" type="number" step="0.01" min="0" max="4" inputmode="decimal" dir="ltr" value="{{ $val('gpa') }}" @if($err('gpa')) aria-invalid="true" @endif>
            @if($err('gpa'))<small class="field-error">{{ $err('gpa') }}</small>@endif
        </label>
        <label class="field">
            <span>Field of study</span>
            <select name="field_of_study">
                <option value="">— Not set —</option>
                @foreach(($studyFields ?? collect()) as $field)
                    <option value="{{ $field }}" {{ (string) $val('field_of_study') === (string) $field ? 'selected' : '' }}>{{ $field }}</option>
                @endforeach
            </select>
        </label>
        <label class="field">
            <span>University language</span>
            <select name="preferred_university_language">
                <option value="">— Not set —</option>
                <option value="en" {{ $val('preferred_university_language') === 'en' ? 'selected' : '' }}>English</option>
                <option value="tr" {{ $val('preferred_university_language') === 'tr' ? 'selected' : '' }}>Turkish</option>
            </select>
        </label>
        <label class="field">
            <span>English level</span>
            <input name="english_level" value="{{ $val('english_level') }}" placeholder="e.g. IELTS 6.5">
        </label>
        <label class="field">
            <span>Budget (USD)</span>
            <input name="budget_usd" type="number" step="1" min="0" inputmode="numeric" dir="ltr" value="{{ $val('budget_usd') }}" @if($err('budget_usd')) aria-invalid="true" @endif>
            @if($err('budget_usd'))<small class="field-error">{{ $err('budget_usd') }}</small>@endif
        </label>
        <label class="field">
            <span>Passport number</span>
            <input name="passport_number" dir="ltr" value="{{ $val('passport_number') }}" autocomplete="off">
        </label>
    </div>
</fieldset>
