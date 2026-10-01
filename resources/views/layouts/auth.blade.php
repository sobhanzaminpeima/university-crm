<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Account Security | Virtue Visa CRM</title>
    <link rel="stylesheet" href="/assets/app.css">
</head>
<body style="display:flex;align-items:center;justify-content:center;min-height:100vh;">
<div class="card auth-card" style="width:min(440px,calc(100% - 32px));">
    @if(session('success'))
        <div class="card" style="border-color:#22c55e;margin-bottom:10px;">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="card" style="border-color:#ef4444;margin-bottom:10px;">{{ $errors->first() }}</div>
    @endif
    @yield('content')
</div>
</body>
</html>
