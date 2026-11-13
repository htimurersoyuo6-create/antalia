@extends('layouts.app')
@section('title', 'Parolamı yenile | Antalya Pet Otel')
@section('content')
<div class="wrap">
  <form class="form auth-form" method="post" action="{{ route('password.email') }}">
    @csrf
    <h1>Parolamı unuttum</h1>
    <p class="hint">Hesabınıza bağlı e-posta adresini girin. Adres kayıtlıysa parola yenileme bağlantısı göndereceğiz.</p>
    @if(session('status'))<p class="notice success" role="status">{{ session('status') }}</p>@endif
    @error('email')<p class="notice error" role="alert">{{ $message }}</p>@enderror
    <label for="reset-email">E-posta adresi</label>
    <input id="reset-email" type="email" name="email" value="{{ old('email') }}" maxlength="255" required autocomplete="email" autofocus>
    <button class="btn" type="submit">Yenileme bağlantısı gönder</button>
    <p class="hint" style="margin-top:14px"><a href="{{ route('login') }}">Giriş sayfasına dön</a></p>
  </form>
</div>
@endsection
