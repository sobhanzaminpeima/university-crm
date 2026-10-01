<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Student Portal Login</title>
    <link rel="stylesheet" href="/assets/app.css">
</head>
<body style="display:flex;align-items:center;justify-content:center;min-height:100vh;">
<div class="card" style="width:min(420px, calc(100vw - 24px));">
    <h2 style="margin-top:0;">Student Portal</h2>
    @if($errors->any())
        <div class="card" style="border-color:#ef4444;margin-bottom:10px;">{{ $errors->first() }}</div>
    @endif
    <form method="POST" action="/portal/login">
        @csrf
        <input type="text" name="login" value="{{ old('login') }}" placeholder="Email or Username" required style="width:100%;margin-bottom:8px;">
        <input type="password" name="password" placeholder="Password" required style="width:100%;margin-bottom:10px;">
        <button style="width:100%;">Login</button>
    </form>
</div>
</body>
</html>
