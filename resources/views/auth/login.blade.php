@extends('layouts.app')
@section('title', 'Giriş Yap | Pet Otel')
@section('content')
<div class="wrap"><form class="form auth-form" method="post" action="{{ route('login') }}">@csrf
<h1>Giriş yap</h1>
@if(session('status'))<p class="notice success" role="status">{{ session('status') }}</p>@endif
@include('auth._social-login')<label>E-posta veya telefon</label><input type="text" name="identifier" value="{{ old('identifier') }}" required autofocus autocomplete="username" placeholder="ornek@mail.com veya 05XX XXX XX XX">
<label for="login-password">Şifre</label>
<div class="password-field">
<input id="login-password" type="password" name="password" required autocomplete="current-password">
<button class="password-toggle" type="button" aria-controls="login-password" aria-pressed="false" aria-label="Şifreyi göster">
<svg class="icon-eye" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
<svg class="icon-eye-off" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m3 3 18 18M10.6 10.6a2 2 0 0 0 2.8 2.8"/><path d="M9.9 5.2A10.8 10.8 0 0 1 12 5c6.4 0 10 7 10 7a16.4 16.4 0 0 1-3.1 3.9M6.2 6.2C3.5 8 2 12 2 12s3.6 7 10 7a10.8 10.8 0 0 0 3-.4"/></svg>
</button>
</div>
<p class="hint" style="margin:-6px 0 14px;text-align:right"><a href="{{ route('password.request') }}">Şifremi unuttum</a></p><label class="chk"><input type="checkbox" name="remember" value="1"> Beni hatırla</label>
<button class="btn">Giriş Yap</button><p class="hint" style="margin-top:14px">Hesabın yok mu? <a href="{{ route('register') }}"><u>Kayıt ol</u></a></p></form></div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const input = document.getElementById('login-password');
    const toggle = document.querySelector('.password-toggle');
    if (!input || !toggle) return;
    toggle.addEventListener('click', function () {
        const show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        toggle.setAttribute('aria-pressed', String(show));
        toggle.setAttribute('aria-label', show ? 'Şifreyi gizle' : 'Şifreyi göster');
        toggle.classList.toggle('is-visible', show);
    });
});
</script>
@endsection
