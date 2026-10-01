@extends('layouts.auth')

@section('content')
<div class="auth-card">
    <h1>Choose a new password</h1>
    <form method="POST" action="/reset-password">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <label>Email</label>
        <input type="email" name="email" required autocomplete="email" value="{{ old('email', $email) }}">
        <label>New password</label>
        <input type="password" name="password" required minlength="12" autocomplete="new-password">
        <label>Confirm password</label>
        <input type="password" name="password_confirmation" required minlength="12" autocomplete="new-password">
        <button type="submit">Change password</button>
    </form>
</div>
@endsection
