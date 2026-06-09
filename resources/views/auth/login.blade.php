@extends('layouts.guest')

@section('content')
<div class="authcard">
    <div class="auth-eye"><a href="{{ url('/') }}">ThePiste</a></div>
    <h1>Welcome back</h1>
    <p class="sub">Sign in to manage your season.</p>

    @if ($errors->any())
        <div class="err-box">{{ $errors->first() }}</div>
    @endif
    @if (session('status'))
        <div class="ok-box">{{ session('status') }}</div>
    @endif

    <form method="POST" action="{{ url('/login') }}">
        @csrf
        <div class="field">
            <label for="email">Email</label>
            <input class="input" id="email" type="email" name="email" value="{{ old('email') }}" required autofocus>
        </div>
        <div class="field">
            <label for="password">Password</label>
            <input class="input" id="password" type="password" name="password" required>
        </div>
        <div class="row-between" style="margin-bottom:18px;">
            <label class="checkline"><input type="checkbox" name="remember"> Remember me</label>
            <a href="{{ url('/forgot-password') }}" style="color:var(--green-ink);text-decoration:none;font-size:13px;">Forgot password?</a>
        </div>
        <button class="btn btn-primary btn-block" type="submit">Sign in</button>
    </form>

    <p class="auth-alt">New here? <a href="{{ url('/register') }}">Create an account</a></p>
</div>
@endsection
