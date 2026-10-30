<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="theme-color" content="#143e35"><meta name="referrer" content="no-referrer"><meta name="robots" content="noindex,nofollow">
    <title>Join your shared AC · CoolMeter</title>
    <link rel="icon" href="/icon.svg"><script src="/theme.js?v=4"></script><link rel="stylesheet" href="/app.css?v=4">
</head>
<body class="auth-page"><section class="auth-card" aria-label="Join shared AC">
    <div class="auth-top"><a href="/" class="auth-brand"><img src="/icon.svg" alt="">CoolMeter.</a>
    <button type="button" class="theme-toggle" data-theme-toggle aria-label="Switch light or dark theme">Light / dark</button></div>
    <h1>One AC. A shared journal.</h1>
    <p>You’ve been invited to manage the same AC as your household. You’ll see the saved history and can start, update, or stop a cooling session.</p>
    @auth
        <p>You’re already signed in as <strong>{{ auth()->user()->email }}</strong>. Sign out first to create a separate account for {{ $invite->email }}.</p>
        <form method="POST" action="{{ route('logout') }}">@csrf<button class="btn btn-primary full-width" type="submit">Sign out</button></form>
    @else
        @if($errors->any())<div class="form-error" role="alert">{{ $errors->first() }}</div>@endif
        <form method="POST" action="{{ url()->current() }}">
            @csrf
            <label>Your name<input name="name" autocomplete="name" maxlength="80" value="{{ old('name') }}" required autofocus></label>
            <label>Invited email address<input name="email" type="email" autocomplete="username" value="{{ $invite->email }}" readonly required></label>
            <label>Create a password<input name="password" type="password" minlength="12" autocomplete="new-password" required></label>
            <label>Confirm password<input name="password_confirmation" type="password" minlength="12" autocomplete="new-password" required></label>
            <p class="field-help">Use at least 12 characters. Keep this password for your own sign-in.</p>
            <button class="btn btn-primary full-width" type="submit">Join the shared AC →</button>
        </form>
        <p class="field-help">Already have an account? <a href="{{ route('login') }}">Sign in</a>. This invitation expires {{ $invite->expires_at->timezone('Asia/Manila')->format('M j, g:i A') }} PHT.</p>
    @endauth
</section></body>
</html>
