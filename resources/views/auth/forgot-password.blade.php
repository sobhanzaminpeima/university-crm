@extends('layouts.auth')

@section('content')
<div class="auth-card">
    <h1>Reset password</h1>
    <p>Enter your account email. If it exists, we will send a secure reset link.</p>
    <form method="POST" action="/forgot-password">
        @csrf
        <label>Email</label>
        <input type="email" name="email" required autocomplete="email" value="{{ old('email') }}">
        <button type="submit">Send reset link</button>
    </form>
    <a href="/login">Back to login</a>
</div>
@endsection
