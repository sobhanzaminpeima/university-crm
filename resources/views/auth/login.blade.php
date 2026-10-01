<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login | Vertue CRM</title>
    <link rel="stylesheet" href="/assets/app.css">
</head>
<body style="display:flex;align-items:center;justify-content:center;min-height:100vh;">
<div class="card" style="width:400px;">
    <h2 style="margin-top:0;">Sign in</h2>
    @if(session('success'))
        <div class="card" style="border-color:#22c55e;margin-bottom:10px;">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="card" style="border-color:#ef4444;margin-bottom:10px;">{{ $errors->first() }}</div>
    @endif
    <form method="POST" action="/login">
        @csrf
        <label>Email or Username</label>
        <input type="text" name="login" value="{{ old('login', old('email')) }}" required style="width:100%;margin:6px 0 10px;">
        <label>Password</label>
        <input type="password" name="password" required style="width:100%;margin:6px 0 14px;">
        <button type="submit" style="width:100%;">Login</button>
    </form>
    <a href="/portal/login" class="secondary" style="display:block;text-align:center;margin-top:10px;padding:10px 12px;border-radius:10px;text-decoration:none;">Student Portal</a>
</div>
</body>
</html>
