@extends('layouts.app')
@php($siteBrand = \App\Models\Setting::al('site_name', config('app.name', 'Pet Otel')))

@section('title', 'Pet Otel Kategorileri | ' . $siteBrand)
@section('desc', $desc)
@section('ogtitle', 'Pet Otel Kategorileri')
@section('ogdesc', $desc)
@push('head')
{!! sema_ld(sema_bolum('WebPage', [
    'name' => 'Pet Otel Kategorileri',
    'description' => $desc,
    'url' => url()->current(),
    'inLanguage' => 'tr-TR',
])) !!}
{!! sema_kirinti([
    ['ad' => 'Ana Sayfa', 'url' => route('home')],
    ['ad' => 'Kategoriler'],
]) !!}
@endpush

@section('content')
<div class="wrap">
  <div class="crumb"><a href="{{ route('home') }}">Ana Sayfa</a> › Kategoriler</div>
  <h1>Pet Otel Kategorileri</h1>
  <p class="giris">{{ $desc }}</p>

  <section>
    <h2>Tüm kategoriler</h2>
    <div class="hub-izgara">
      @foreach($anaKategoriler as $k)
        <a class="hub-kutu" href="{{ $k['url'] }}">
          <b aria-hidden="true">{{ $k['ikon'] }}</b>
          <strong>{{ $k['ad'] }}</strong>
          <span>{{ $k['adet'] }} ilan</span>
        </a>
      @endforeach
    </div>
  </section>

  @if($ilceler->isNotEmpty())
  <section>
    <h2>İlçeye göre pet otelleri</h2>
    <div class="hub-izgara">
      @foreach($ilceler as $d)
        <a class="hub-kutu" href="{{ url('/' . $d->slug) }}">
          <b aria-hidden="true">&#128205;</b>
          <strong>{{ $d->name }}</strong>
          <span>{{ $d->otel_adet }} pet otel</span>
        </a>
      @endforeach
    </div>
  </section>
  @endif

  @if($odaTipleri->isNotEmpty())
  <section>
    <h2>Oda tipine göre</h2>
    <div class="hub-izgara">
      @foreach($odaTipleri as $o)
        <a class="hub-kutu" href="{{ url('/oda-tipi/' . $o->slug) }}">
          <b aria-hidden="true">&#128716;</b>
          <strong>{{ $o->ad }}</strong>
          <span>{{ $o->otel_adet }} tesiste</span>
        </a>
      @endforeach
    </div>
  </section>
  @endif

  <section>
    <h2>Diğer rehberler</h2>
    <div class="hub-izgara">
      <a class="hub-kutu" href="{{ route('fiyatlar') }}"><b aria-hidden="true">&#128176;</b><strong>Fiyat Rehberi</strong><span>Günlük ücret aralıkları</span></a>
      <a class="hub-kutu" href="{{ route('pages.show', 'pet-otel-nedir') }}"><b aria-hidden="true">&#128161;</b><strong>Pet Otel Nedir?</strong><span>Konaklama nasıl işler</span></a>
      <a class="hub-kutu" href="{{ route('pages.show', 'nasil-calisir') }}"><b aria-hidden="true">&#128203;</b><strong>Nasıl Çalışır?</strong><span>Rezervasyon adımları</span></a>
      <a class="hub-kutu" href="{{ route('blog.index') }}"><b aria-hidden="true">&#128221;</b><strong>Blog</strong><span>Pet otel rehberleri</span></a>
    </div>
  </section>
</div>
@endsection
