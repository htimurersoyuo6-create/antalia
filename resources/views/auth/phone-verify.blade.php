@extends('layouts.app')
@section('title','Telefon Doğrulama | Antalya Pet Otel')
@section('content')
<div class="wrap page"><form class="form auth-form" method="post" action="{{ route('register.phone.verify.store') }}">@csrf
<h1>Telefonunuzu doğrulayın</h1><p class="hint">{{ $maskedPhone }} numarasına gönderilen 6 haneli kodu girin. Kod 10 dakika geçerlidir.</p>
<label for="phone-code">Doğrulama kodu</label><input id="phone-code" name="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" minlength="6" maxlength="6" required autofocus>
<button class="btn" type="submit">Telefonu doğrula</button></form>
<form method="post" action="{{ route('register.phone.resend') }}" class="wrap" style="max-width:590px;margin:10px auto">@csrf<button class="btn ghost" type="submit">Yeni kod gönder</button></form>
</div>
@endsection
