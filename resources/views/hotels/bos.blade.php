@extends('layouts.app')
@section('title', 'Sonuç Bulunamadı | Antalya Pet Otel')
@section('robots', 'noindex, follow')
@section('content')
<div class="wrap">
<div class="crumb"><a href="{{ route('home') }}">Ana Sayfa</a> › Sonuç bulunamadı</div>
<h1>Aradığınız kriterlere uygun tesis bulunamadı</h1>
<p class="sub">Seçtiğiniz bölge veya filtre için şu anda listelenmiş bir tesis yok.</p>
<div class="chips">
  <a class="chip" href="{{ route('hotels.all') }}">Tüm Antalya pet otelleri</a>
  <a class="chip" href="{{ route('hotels.dogs') }}">Köpek otelleri</a>
  <a class="chip" href="{{ route('hotels.cats') }}">Kedi otelleri</a>
  <a class="chip" href="{{ route('fiyatlar') }}">Fiyat karşılaştırması</a>
  <a class="chip" href="{{ route('blog.index') }}">Blog</a>
  <a class="chip" href="{{ route('contact') }}">İletişim</a>
</div>
<p class="hint" style="margin-top:20px">
  Aradığınız tesis listede yoksa bize yazın; ekleyelim.
  <a href="{{ route('contact') }}">İletişim formu</a> ·
  <a href="{{ route('partner.create') }}">Tesisimi kaydet</a>
</p>
</div>
@endsection