@extends('layouts.app')
@php
    $siteBrand = \App\Models\Setting::al('site_name', config('app.name', 'Pet Otel'));
    $adet     = $hoteller->count();
    $kopekler = $hoteller->filter(fn($h) => (bool) $h->accepts_dogs);
    $kediler  = $hoteller->filter(fn($h) => (bool) $h->accepts_cats);
    $fiyatlar = [];
    foreach ($hoteller as $h) foreach ($h->rooms as $o) {
        if ((float) $o->base_price > 0) $fiyatlar[] = (float) $o->base_price;
    }
    $enUcuz   = $fiyatlar ? min($fiyatlar) : null;
    $enPahali = $fiyatlar ? max($fiyatlar) : null;

    $konu = seo_metin($ilce->name . ' ilçesindeki pet otelleri, kedi ve köpek konaklama tesisleri', 70, false);

    $giris = $ilce->name . ' ilçesinde konaklama sunan ' . $adet . ' pet otel bulunuyor. '
        . ($kopekler->count() > 0 ? $kopekler->count() . ' tesis köpek, ' : '')
        . ($kediler->count() > 0 ? $kediler->count() . ' tesis kedi konaklaması kabul ediyor. ' : '')
        . ($enUcuz !== null
            ? 'Günlük oda fiyatları ' . number_format($enUcuz, 0, ',', '.') . ' TL ile '
              . number_format($enPahali, 0, ',', '.') . ' TL arasında değişiyor. '
            : '')
        . 'Aşağıdaki liste oda tipi, kabul edilen hayvan türleri ve günlük ücreti birlikte gösterir; '
        . 'karşılaştırma yaparken bu üç başlığı birlikte değerlendirin.';

    $navOge = [];
    foreach ($hoteller as $h) $navOge[] = ['url' => route('hotels.show', $h->slug), 'ad' => $h->name];
@endphp
@section('title', $ilce->name . ' Pet Otel Fiyatları ve Köpek Pansiyonu | ' . $siteCity . ' | ' . $siteBrand)
@section('desc', seo_metin($giris, 158))
@section('ogtitle', $ilce->name . ' Pet Otel Fiyatları ve Köpek Pansiyonu')
@section('ogdesc', seo_metin($giris, 200))
@push('head')
{!! seo_liste_sema($ilce->name . ' Pet Otelleri', url()->current(), $giris, $navOge) !!}
{!! sema_kirinti([
    ['ad' => 'Ana Sayfa', 'url' => route('home')],
    ['ad' => $siteCity . ' Pet Otelleri', 'url' => route('hotels.all')],
    ['ad' => $ilce->name],
]) !!}
{!! sema_sss($sss) !!}
@endpush
@section('content')
<div class="wrap">
<div class="crumb"><a href="{{ route('home') }}">Ana Sayfa</a> ›
  <a href="{{ route('hotels.all') }}">Pet Otelleri</a> › {{ $ilce->name }}</div>

<h1>{{ $ilce->name }} Pet Otel Fiyatları ve Köpek Pansiyonları</h1>
<p class="sub">{{ $adet }} tesis · {{ $kopekler->count() }} köpek · {{ $kediler->count() }} kedi</p>

<article class="prose" style="max-width:820px">
{!! makale_guvenli_html('<p>' . $giris . '</p>') !!}
</article>

<div class="grid">@forelse($hoteller as $h)@include('hotels._card')@empty
  <p>Bu ilçede şu anda listelenen bir pet otel yok.</p>
@endforelse</div>

@if($sss)
<h2>{{ $ilce->name }} Pet Otel Sıkça Sorulan Sorular</h2>
<div class="box">
@foreach($sss as $q)
  <details class="faq"><summary>{{ $q['soru'] }}</summary><p>{{ $q['cevap'] }}</p></details>
@endforeach
</div>
@endif

@php
    $bolgeler = collect(config('local_seo.localities', []))->where('district', $ilce->slug);
@endphp
@if($bolgeler->isNotEmpty())
<h2>{{ $ilce->name }} içindeki bölgeler</h2>
<div class="chips">
  @foreach($bolgeler as $bolge)<a class="chip" href="{{ route('local-seo.show', [$bolge['district'], $bolge['slug']]) }}">{{ $bolge['name'] }} pet otel</a>@endforeach
</div>
@endif

@if($yakin->isNotEmpty())
<h2>{{ $siteCity }}’da diğer ilçeler</h2>
<div class="chips">
  @foreach($yakin as $y)<a class="chip" href="{{ url('/' . $y->slug) }}">{{ $y->name }}</a>@endforeach
</div>
@endif
</div>
@endsection
