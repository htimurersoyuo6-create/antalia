@extends('layouts.app')
@section('title', 'Kayıt Ol | Pet Otel')
@section('content')
<div class="wrap"><form class="form auth-form" method="post" action="{{ route('register') }}">@csrf
<h1>Güvenli bir hesap oluşturun</h1>
@include('auth._social-login')<p class="hint">Rezervasyonlarınızı, dostlarınızı ve siparişlerinizi tek yerden yönetin.</p>
<label>Ad Soyad</label><input type="text" name="name" value="{{ old('name') }}" autocomplete="name" required>
<label>E-posta <small>(isteğe bağlı)</small></label><input id="register-email" type="email" name="email" value="{{ old('email') }}" autocomplete="email">
<div id="phone-fields"><label>Cep telefonu</label><input id="register-phone" type="tel" name="phone" value="{{ old('phone') }}" autocomplete="tel" inputmode="tel" placeholder="05XX XXX XX XX"><p class="hint">E-posta kullanmıyorsanız telefon numaranızı SMS ile doğrulamanız gerekir.</p>

<label>Adres <small>(isteğe bağlı; CRM ve transfer iletişimi için)</small></label><textarea name="address" rows="2" maxlength="500" autocomplete="street-address">{{ old('address') }}</textarea>
<p class="hint">Şifre en az 8 karakter olmalı; harf, rakam ve özel karakter kullanabilirsiniz.</p>
<div class="row2"><div><label>Şifre</label><input type="password" name="password" autocomplete="new-password" required></div><div><label>Şifre (tekrar)</label><input type="password" name="password_confirmation" autocomplete="new-password" required></div></div>
<label class="chk"><input type="checkbox" name="kvkk" value="1" required> <span><a href="{{ route('pages.show','gizlilik') }}" target="_blank" rel="noopener"><u>Gizlilik / KVKK metnini</u></a> okudum, kabul ediyorum.</span></label>
@include('partials.captcha',['captchaKey'=>'register'])
<button class="btn">Hesap Oluştur</button>
<p class="hint" style="margin-top:14px">Hesabınız var mı? <a href="{{ route('login') }}"><u>Giriş yapın</u></a></p></form></div>
<script>(()=>{const e=document.getElementById('register-email'),box=document.getElementById('phone-fields'),p=document.getElementById('register-phone');if(!e||!box)return;const update=()=>{const need=!e.value.trim();box.hidden=!need;p.required=need};e.addEventListener('input',update);update()})();</script>
@endsection
