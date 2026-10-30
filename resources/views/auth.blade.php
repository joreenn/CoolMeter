<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="theme-color" content="#143e35"><meta name="referrer" content="no-referrer">
    <title>{{ $setup ? 'Set up your journal' : 'Sign in' }} · CoolMeter</title>
    <link rel="icon" href="/icon.svg"><link rel="stylesheet" href="/app.css?v=2">
    <style>
        body{min-height:100dvh;display:grid;place-items:center;padding:24px;background:radial-gradient(ellipse at top,#e4efe4,#f7f8fa 70%)}
        .auth-card{width:min(100%,420px);padding:36px;background:#fff;border:1px solid #e2eade;border-radius:20px;box-shadow:0 18px 70px #143e350b}
        .auth-brand{display:flex;gap:10px;align-items:center;font-size:25px;font-weight:650;letter-spacing:-1px}.auth-brand img{width:40px;height:40px;border-radius:11px}
        .auth-card h1{font-size:25px;margin:32px 0 10px}.auth-card p{font-size:13px;color:#728070;line-height:1.8;margin-bottom:24px}
        .auth-card label{font-size:12px;margin-top:19px}.auth-card input{font-size:14px;padding:13px}.auth-card .remember{display:flex;gap:9px;align-items:center;color:#728070;font-weight:400}.remember input{width:16px;min-height:16px;margin:0;accent-color:#23634d}.auth-card .btn{font-size:12px;margin-top:25px}.auth-foot{font-size:10px!important;text-align:center;margin:23px 0 0!important}
    </style>
</head>
<body><section class="auth-card" aria-label="CoolMeter account">
    <a href="/" class="auth-brand"><img src="/icon.svg" alt="">CoolMeter<span style="color:#8dbb99;margin-left:-8px">.</span></a>
    <h1>{{ $setup ? 'Your journal, just for you.' : 'Welcome back.' }}</h1>
    <p>{{ $setup ? 'Choose the email and password you’ll use to open your cooling journal on any device.' : 'Sign in to track your cooling, wherever you are.' }}</p>
    @if($errors->any())<div class="form-error" role="alert">{{ $errors->first() }}</div>@endif
    <form method="POST" action="{{ $setup ? url()->current() : route('login') }}">
        @csrf
        <label>Email address<input name="email" type="email" autocomplete="username" maxlength="254" value="{{ old('email') }}" required autofocus></label>
        <label>Password<input name="password" type="password" autocomplete="{{ $setup ? 'new-password' : 'current-password' }}" {{ $setup ? 'minlength=12' : '' }} required></label>
        @if($setup)
            <label>Confirm password<input name="password_confirmation" type="password" minlength="12" autocomplete="new-password" required></label>
            <p class="field-help" style="margin:10px 0 0">Use at least 12 characters. Keep your password in a safe place.</p>
        @else
            <label class="remember"><input type="checkbox" name="remember" value="1">Keep me signed in on this device</label>
        @endif
        <button class="btn btn-primary full-width" type="submit">{{ $setup ? 'Create my private journal' : 'Sign in to my journal' }} →</button>
    </form>
    <p class="auth-foot">Your personal energy journal · LG LA100ENT</p>
</section></body></html>
