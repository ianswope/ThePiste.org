@extends('layouts.guest')

@section('content')
<div class="authcard">
    <div class="auth-eye"><a href="{{ url('/') }}">ThePiste</a></div>
    <h1>Create your account</h1>
    <p class="sub">Plan a fencer's season, track results, hit the goal.</p>

    @if ($errors->any())
        <div class="err-box">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ url('/register') }}">
        @csrf
        <div class="field">
            <label for="name">Your name</label>
            <input class="input" id="name" type="text" name="name" value="{{ old('name') }}" required autofocus>
        </div>
        <div class="field">
            <label for="email">Email</label>
            <input class="input" id="email" type="email" name="email" value="{{ old('email') }}" required>
        </div>
        <div class="field">
            <label for="password">Password</label>
            <input class="input" id="password" type="password" name="password" required>
        </div>
        <div class="field">
            <label for="password_confirmation">Confirm password</label>
            <input class="input" id="password_confirmation" type="password" name="password_confirmation" required>
        </div>
        <button class="btn btn-primary btn-block" type="submit">Create account</button>
    </form>

    <p class="auth-alt">Already have an account? <a href="{{ url('/login') }}">Sign in</a></p>
</div>
@endsection
