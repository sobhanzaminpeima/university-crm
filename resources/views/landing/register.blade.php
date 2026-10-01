<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Register Company | Vertue CRM</title>
    <link rel="stylesheet" href="/assets/app.css">
</head>
<body style="display:flex;align-items:center;justify-content:center;min-height:100vh;background:#f1f5f9;">
<div class="card" style="width:min(720px,95vw);">
    <h2 style="margin-top:0;">Register Your Company</h2>
    @if($errors->any())
        <div class="card" style="border-color:#ef4444;margin-bottom:10px;">{{ $errors->first() }}</div>
    @endif
    <form method="POST" action="/register">
        @csrf
        <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;">
            <div><label>Company Name</label><input name="company_name" required value="{{ old('company_name') }}"></div>
            <div><label>Full Name</label><input name="full_name" required value="{{ old('full_name') }}"></div>
            <div><label>Email</label><input type="email" name="email" required value="{{ old('email') }}"></div>
            <div><label>Phone</label><input name="phone" value="{{ old('phone') }}"></div>
            <div style="grid-column:1/-1;"><label>Password</label><input type="password" name="password" required></div>
            <div style="grid-column:1/-1;">
                <label>Plan</label>
                <select name="plan_id" required>
                    @foreach($plans as $plan)
                        <option value="{{ $plan->id }}" {{ (int)old('plan_id', $selectedPlanId) === (int)$plan->id ? 'selected' : '' }}>
                            {{ $plan->name }} - {{ $plan->currency }} {{ number_format((float) $plan->price, 0) }} / {{ $plan->duration_months }}m
                        </option>
                    @endforeach
                </select>
            </div>
        </div>
        <div style="display:flex;gap:8px;margin-top:12px;">
            <button type="submit">Create Account</button>
            <a class="secondary" href="/" style="text-decoration:none;padding:10px 14px;border-radius:10px;">Cancel</a>
        </div>
    </form>
</div>
</body>
</html>

