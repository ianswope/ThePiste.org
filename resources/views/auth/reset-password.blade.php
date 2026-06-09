@extends('layouts.guest')

@section('content')
<div class="authcard">
    <div class="auth-eye"><a href="{{ url('/') }}">ThePiste</a></div>
    <h1>Choose a new password</h1>

    @if ($errors->any())
        <div class="err-box">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ url('/reset-password') }}">
        @csrf
        <input type="hidden" name="token" value="{{ $request->route('token') }}">
        <div class="field">
            <label for="email">Email</label>
            <input class="input" id="email" type="email" name="email" value="{{ old('email', $request->email) }}" required autofocus>
        </div>
        <div class="field">
            <label for="password">New password</label>
            <input class="input" id="password" type="password" name="password" required>
        </div>
        <div class="field">
            <label for="password_confirmation">Confirm password</label>
            <input class="input" id="password_confirmation" type="password" name="password_confirmation" required>
        </div>
        <button class="btn btn-primary btn-block" type="submit">Reset password</button>
    </form>
</div>
@endsection
