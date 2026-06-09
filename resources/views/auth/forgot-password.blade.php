@extends('layouts.guest')

@section('content')
<div class="authcard">
    <div class="auth-eye"><a href="{{ url('/') }}">ThePiste</a></div>
    <h1>Reset password</h1>
    <p class="sub">Enter your email and we'll send a reset link.</p>

    @if (session('status'))
        <div class="ok-box">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="err-box">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ url('/forgot-password') }}">
        @csrf
        <div class="field">
            <label for="email">Email</label>
            <input class="input" id="email" type="email" name="email" value="{{ old('email') }}" required autofocus>
        </div>
        <button class="btn btn-primary btn-block" type="submit">Email reset link</button>
    </form>

    <p class="auth-alt"><a href="{{ url('/login') }}">Back to sign in</a></p>
</div>
@endsection
