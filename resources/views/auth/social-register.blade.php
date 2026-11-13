@extends('layouts.app')
@section('title', 'Hesabınızı Tamamlayın | Pet Otel')
@section('content')
<div class="wrap page">
  <form class="form auth-form social-register-form" method="post" action="{{ route('social.register.store') }}">
    @csrf
    <span class="eyebrow">GÜVENLİ HESAP OLUŞTURMA</span>
    <h1>Hesabınızı tamamlayın</h1>
    <p class="hint">{{ ucfirst($pending['provider']) }} ile doğrulanan bilgileriniz hesabınız için kullanılacak.</p>
    <div class="social-identity-summary">
      <span>{{ $pending['provider'] === 'apple' ? '●' : 'G' }}</span>
      <div><strong>{{ $pending['name'] }}</strong><small>{{ $pending['email'] }}</small></div>
    </div>
    <p class="hint">Devam ederek Antalya Pet Otel hesabınız oluşturulur. Bilgiler yalnızca hesabınızı ve rezervasyonlarınızı yönetmek için kullanılır.</p>
    <label class="chk"><input type="checkbox" name="kvkk" value="1" required> <span><a href="{{ route('pages.show','gizlilik') }}" target="_blank" rel="noopener"><u>Gizlilik / KVKK metnini</u></a> okudum, kabul ediyorum.</span></label>
    @include('partials.captcha',['captchaKey'=>'social-register'])
    <button class="btn" type="submit">Hesap oluştur ve devam et</button>
    <p class="hint" style="margin-top:14px"><a href="{{ route('login') }}"><u>Giriş sayfasına dön</u></a></p>
  </form>
</div>
@endsection
