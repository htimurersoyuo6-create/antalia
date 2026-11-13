@extends('layouts.app')
@php
    $brand = \App\Models\Setting::al('site_name', config('app.name', 'Antalya Pet Otel'));
    $dogs = $hotels->where('accepts_dogs', true)->count();
    $cats = $hotels->where('accepts_cats', true)->count();
    $items = $hotels->map(fn ($hotel) => ['url' => route('hotels.show', $hotel->slug), 'ad' => $hotel->name])->all();
    $title = $locality['intent'] . ' | Kedi ve Köpek Oteli | ' . $district->name;
@endphp
@section('title', $title . ' | ' . $brand)
@section('desc', seo_metin($summary, 158))
@section('ogtitle', $title)
@section('ogdesc', seo_metin($summary, 200))
@push('head')
{!! seo_liste_sema($title, url()->current(), $summary, $items) !!}
{!! sema_kirinti([
    ['ad' => 'Ana Sayfa', 'url' => route('home')],
    ['ad' => 'Antalya Pet Otelleri', 'url' => route('hotels.all')],
    ['ad' => $district->name, 'url' => route('districts.show', $district->slug)],
    ['ad' => $locality['name']],
]) !!}
{!! sema_sss($faq) !!}
@endpush
@section('content')
<div class="wrap">
  <nav class="crumb" aria-label="Sayfa yolu"><a href="{{ route('home') }}">Ana Sayfa</a> › <a href="{{ route('hotels.all') }}">Antalya Pet Otelleri</a> › <a href="{{ route('districts.show', $district->slug) }}">{{ $district->name }}</a> › {{ $locality['name'] }}</nav>
  <h1>{{ $locality['intent'] }} ve Pet Otel Seçenekleri</h1>
  <p class="sub">{{ $district->name }} çevresinde {{ $hotels->count() }} aktif tesis · {{ $dogs }} köpek · {{ $cats }} kedi konaklama seçeneği</p>

  <article class="prose" style="max-width:820px">
    <p>{{ $summary }}</p>
    <h2>{{ $locality['name'] }} yakınında pet otel seçerken</h2>
    <p>{{ $locality['name'] }} için arama yaparken tesisin mahalleye olan ulaşımını, kabul ettiği hayvan türünü ve oda koşullarını karşılaştırın. Bu sayfadaki tesisler {{ $district->name }} ilçesindeki aktif ilanlardan gelir; tesislerin kesin konumunu ve rezervasyon uygunluğunu tesis sayfasından kontrol edin.</p>
  </article>

  <h2>{{ $locality['name'] }} çevresindeki tesisler</h2>
  <div class="grid">@foreach($hotels as $h) @include('hotels._card') @endforeach</div>

  <h2>{{ $locality['name'] }} pet otel sıkça sorulan sorular</h2>
  <div class="box">@foreach($faq as $item)<details class="faq"><summary>{{ $item['soru'] }}</summary><p>{{ $item['cevap'] }}</p></details>@endforeach</div>

  @if($nearby->isNotEmpty())
  <h2>{{ $district->name }} içindeki diğer bölgeler</h2>
  <div class="chips">@foreach($nearby as $item)<a class="chip" href="{{ route('local-seo.show', [$item['district'], $item['slug']]) }}">{{ $item['name'] }} pet otel</a>@endforeach</div>
  @endif
</div>
@endsection
