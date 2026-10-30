<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="theme-color" content="#143e35"><meta name="referrer" content="no-referrer">
    <title>{{ $setup ? 'Set up your journal' : 'Sign in' }} · CoolMeter</title>
    <script src="/theme.js?v=4"></script>
    <link rel="icon" href="/icon.svg"><link rel="stylesheet" href="/app.css?v=4">
</head>
<body class="auth-page"><section class="auth-card" aria-label="CoolMeter account">
    <div class="auth-top"><a href="/" class="auth-brand"><img src="/icon.svg" alt="">CoolMeter<span class="brand-dot">.</span></a><button type="button" class="theme-toggle" data-theme-toggle aria-label="Switch color theme">Change theme</button></div>
    <h1>{{ $setup ? 'Your journal, just for you.' : 'Welcome back.' }}</h1>
    <p>{{ $setup ? 'Choose the email and password you’ll use to open your cooling journal on any device.' : 'Sign in to track your cooling, wherever you are.' }}</p>
    @if($errors->any())<div class="form-error" role="alert">{{ $errors->first() }}</div>@endif
    <form method="POST" action="{{ $setup ? url()->current() : route('login') }}">
        @csrf
        <label>Email address<input name="email" type="email" autocomplete="username" maxlength="254" value="{{ old('email') }}" required autofocus></label>
        <label>Password<input name="password" type="password" autocomplete="{{ $setup ? 'new-password' : 'current-password' }}" {{ $setup ? 'minlength=12' : '' }} required></label>
        @if($setup)
            <label>Confirm password<input name="password_confirmation" type="password" minlength="12" autocomplete="new-password" required></label>
            <p class="field-help">Use at least 12 characters. Keep your password in a safe place.</p>
        @else
            <label class="remember"><input type="checkbox" name="remember" value="1">Keep me signed in on this device</label>
        @endif
        <button class="btn btn-primary full-width" type="submit">{{ $setup ? 'Create my private journal' : 'Sign in to my journal' }} →</button>
    </form>
    <p class="auth-foot">Your personal energy journal · LG LA100ENT</p>
</section></body></html>
