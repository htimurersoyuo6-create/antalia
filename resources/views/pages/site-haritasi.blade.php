@extends('layouts.app')
@php($siteBrand = \App\Models\Setting::al('site_name', config('app.name', 'Pet Otel')))

@section('title', 'Site Haritası | ' . $siteBrand)
@section('desc', $desc)
@section('ogtitle', 'Site Haritası')
@section('ogdesc', $desc)
@push('head')
{!! sema_ld(sema_bolum('WebPage', [
    'name' => 'Site Haritası',
    'description' => $desc,
    'url' => url()->current(),
    'inLanguage' => 'tr-TR',
])) !!}
{!! sema_kirinti([
    ['ad' => 'Ana Sayfa', 'url' => route('home')],
    ['ad' => 'Site Haritası'],
]) !!}
@endpush

@section('content')
<div class="wrap">
  <div class="crumb"><a href="{{ route('home') }}">Ana Sayfa</a> › Site Haritası</div>
  <h1>Site Haritası</h1>
  <p class="giris">{{ $desc }}</p>

  @foreach($bloklar as $baslik => $baglantilar)
    <section class="harita-blok">
      <h2>{{ $baslik }}</h2>
      <ul class="harita-liste">
        @foreach($baglantilar as $b)
          <li><a href="{{ $b['url'] }}">{{ $b['ad'] }}</a></li>
        @endforeach
      </ul>
    </section>
  @endforeach
</div>
@endsection
