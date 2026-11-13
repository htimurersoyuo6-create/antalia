@extends('layouts.app')

@section('title', 'Sahiplendirme geçici olarak kullanılamıyor | Antalya Pet Otel')
@section('desc', 'Sahiplendirme ilanlarına geçici olarak erişilemiyor. Lütfen daha sonra tekrar deneyin.')
@section('robots', 'noindex, nofollow')

@section('content')
<div class="wrap">
  <div class="crumb"><a href="{{ route('home') }}">Ana Sayfa</a> › Sahiplendirme</div>
  <section class="hata-sayfa" role="status">
    <div class="hata-kod" aria-hidden="true">503</div>
    <h1>Sahiplendirme hizmeti geçici olarak kullanılamıyor</h1>
    <p>İlanlara şu anda erişemiyoruz. Lütfen daha sonra tekrar deneyin.</p>
    <a class="btn" href="{{ route('home') }}">Ana sayfa</a>
  </section>
</div>
@endsection
