@extends('layouts.app')
@section('title', 'Yeni parola belirle | Antalya Pet Otel')
@section('content')
<div class="wrap">
  <form class="form auth-form" method="post" action="{{ route('password.update') }}">
    @csrf
    <h1>Yeni parola belirle</h1>
    <p class="hint">Güvenliğiniz için en az 8 karakter kullanın.</p>
    <input type="hidden" name="token" value="{{ $token }}">
    @if($errors->any())<div class="notice error" role="alert">@foreach($errors->all() as $message)<p>{{ $message }}</p>@endforeach</div>@endif
    <label for="reset-email">E-posta adresi</label>
    <input id="reset-email" type="email" name="email" value="{{ old('email', $email) }}" maxlength="255" required autocomplete="email">
    <label for="new-password">Yeni parola</label>
    <input id="new-password" type="password" name="password" required minlength="8" autocomplete="new-password">
    <label for="new-password-confirmation">Yeni parola (tekrar)</label>
    <input id="new-password-confirmation" type="password" name="password_confirmation" required minlength="8" autocomplete="new-password">
    <button class="btn" type="submit">Parolayı güncelle</button>
    <p class="hint" style="margin-top:14px"><a href="{{ route('login') }}">Giriş sayfasına dön</a></p>
  </form>
</div>
@endsection
