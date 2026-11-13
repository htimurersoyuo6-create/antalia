@extends('layouts.app')
@php
    $fmtTL = $fmt;
    $adet  = count($tesisler);
    $siteBrand = \App\Models\Setting::al('site_name', config('app.name', 'Pet Otel'));

    $aciklama = ($siteCity ?: 'Seçilen bölgedeki') . ' ' . $adet
        . ' pet otelin gecelik oda fiyatlarını ve ilçe dağılımını karşılaştırın. '
        . 'Güncel fiyatı rezervasyon öncesi tesisle teyit edin.';

    $ogeler = [];
    foreach ($tesisler as $t)
        $ogeler[] = ['url' => route('hotels.show', $t['otel']->slug), 'ad' => $t['otel']->name];
@endphp
@section('title', ($siteCity ?: 'Pet Otel') . ' Pet Oteli Fiyatları ve Karşılaştırması | ' . $siteBrand)
@section('desc', seo_metin($aciklama, 158))
@section('ogtitle', ($siteCity ?: 'Pet Otel') . ' Pet Oteli Fiyatları ve Karşılaştırması')
@section('ogdesc', seo_metin($aciklama, 200))
@push('head')
{!! seo_liste_sema(($siteCity ?: 'Pet Otel') . ' Pet Otel Fiyatları', url()->current(), $aciklama, $ogeler) !!}
{!! sema_kirinti([
    ['ad' => 'Ana Sayfa', 'url' => route('home')],
    ['ad' => 'Pet Otelleri', 'url' => route('hotels.all')],
    ['ad' => 'Fiyatlar'],
]) !!}
{!! sema_sss($sss) !!}
@endpush
@section('content')
<div class="wrap pricing-page">
<div class="crumb"><a href="{{ route('home') }}">Ana Sayfa</a> ›
  <a href="{{ route('hotels.all') }}">Pet Otelleri</a> › Fiyatlar</div>

<h1>{{ $siteCity ?: 'Pet Otel' }} Pet Otel Fiyatları</h1>
<p class="sub">{{ $adet }} tesis · {{ $fmt($enUcuz) }} TL - {{ $fmt($enPahali) }} TL / gece</p>

<article class="prose" style="max-width:840px">
{!! makale_guvenli_html('<p>' . e($aciklama) . '</p>') !!}
<p>Aşağıdaki tabloda her tesisin kendi oda tiplerinden hesaplanan günlük en ucuz ve en yüksek
ücret yer alıyor. Ücretler işletme panelinden girilir ve sayfa her açılışta güncel kayıttan
okunur. Uzun konaklama ve ek hizmetler için işletmeyle doğrudan görüşmeniz gerekir.</p>
</article>

@if($bantlar)
<h2>Fiyat aralığına göre oda dağılımı</h2>
<div class="box pricing-table-wrap pricing-table-bands">
  <table class="pricing-table" style="width:100%;border-collapse:collapse">
    <thead><tr>
      <th style="text-align:left;padding:8px;border-bottom:1px solid #e5e7eb">Günlük ücret aralığı</th>
      <th style="text-align:left;padding:8px;border-bottom:1px solid #e5e7eb">Oda tipi sayısı</th>
    </tr></thead>
    <tbody>
    @foreach($bantlar as $b)
      <tr>
        <td style="padding:8px;border-bottom:1px solid #f1f5f9">
          <span>{{ $b['ad'] }}</span>
        </td>
        <td style="padding:8px;border-bottom:1px solid #f1f5f9">{{ $b['adet'] }}</td>
      </tr>
    @endforeach
    </tbody>
  </table>
</div>
@endif

<h2>Hayvan türüne göre fiyat</h2>
<div class="grid">
  <div class="box">
    <b>Kedi oteli fiyatları</b>
    <p class="sub" style="margin:6px 0">
      @if($kediEnUcuz !== null)
        {{ $kediAdet }} odada en ucuz {{ $fmt($kediEnUcuz) }} TL / gece
      @else Kedi kabul eden listelenmiş tesis yok @endif
    </p>
    <a class="chip" href="{{ route('hotels.cats') }}">Kedi otellerini gör</a>
  </div>
  <div class="box">
    <b>Köpek oteli fiyatları</b>
    <p class="sub" style="margin:6px 0">
      @if($kopekEnUcuz !== null)
        {{ $kopekAdet }} odada en ucuz {{ $fmt($kopekEnUcuz) }} TL / gece
      @else Köpek kabul eden listelenmiş tesis yok @endif
    </p>
    <a class="chip" href="{{ route('hotels.dogs') }}">Köpek otellerini gör</a>
  </div>
</div>

@if($ilceFiyat)
<h2>İlçeye göre en ucuz günlük ücret</h2>
<div class="box pricing-table-wrap pricing-table-districts">
  <table class="pricing-table" style="width:100%;border-collapse:collapse">
    <thead><tr>
      <th style="text-align:left;padding:8px;border-bottom:1px solid #e5e7eb">İlçe</th>
      <th style="text-align:left;padding:8px;border-bottom:1px solid #e5e7eb">Tesis sayısı</th>
      <th style="text-align:left;padding:8px;border-bottom:1px solid #e5e7eb">En ucuz günlük ücret</th>
    </tr></thead>
    <tbody>
    @foreach($ilceFiyat as $il => $ozet)
      <tr>
        <td style="padding:8px;border-bottom:1px solid #f1f5f9">
          <a href="{{ url('/' . $ozet['slug']) }}">{{ $il }}</a>
        </td>
        <td style="padding:8px;border-bottom:1px solid #f1f5f9">{{ $ozet['adet'] }}</td>
        <td style="padding:8px;border-bottom:1px solid #f1f5f9">{{ $fmt($ozet['min']) }} TL</td>
      </tr>
    @endforeach
    </tbody>
  </table>
</div>
@endif

<h2>Tesis bazında günlük fiyat</h2>
<div class="box pricing-table-wrap pricing-table-hotels">
  <table class="pricing-table" style="width:100%;border-collapse:collapse">
    <thead><tr>
      <th style="text-align:left;padding:8px;border-bottom:1px solid #e5e7eb">Tesis</th>
      <th style="text-align:left;padding:8px;border-bottom:1px solid #e5e7eb">İlçe</th>
      <th style="text-align:left;padding:8px;border-bottom:1px solid #e5e7eb">Kabul</th>
      <th style="text-align:right;padding:8px;border-bottom:1px solid #e5e7eb">En ucuz</th>
      <th style="text-align:right;padding:8px;border-bottom:1px solid #e5e7eb">En yüksek</th>
    </tr></thead>
    <tbody>
    @foreach($tesisler as $t)
      <tr>
        <td style="padding:8px;border-bottom:1px solid #f1f5f9">
          <a href="{{ route('hotels.show', $t['otel']->slug) }}">{{ $t['otel']->name }}</a>
        </td>
        <td style="padding:8px;border-bottom:1px solid #f1f5f9">{{ $t['ilce'] }}</td>
        <td style="padding:8px;border-bottom:1px solid #f1f5f9">
          @if($t['kopek']) Köpek @endif
          @if($t['kedi']) Kedi @endif
        </td>
        <td style="padding:8px;border-bottom:1px solid #f1f5f9;text-align:right">{{ $fmt($t['min']) }} ₺</td>
        <td style="padding:8px;border-bottom:1px solid #f1f5f9;text-align:right">{{ $fmt($t['max']) }} ₺</td>
      </tr>
    @endforeach
    </tbody>
  </table>
</div>

@if(count($sss))
<h2>Pet oteli fiyatları hakkında sıkça sorulan sorular</h2>
<div class="box">
@foreach($sss as $q)
  <details class="faq"><summary>{{ $q['soru'] }}</summary><p>{{ $q['cevap'] }}</p></details>
@endforeach
</div>
@endif
</div>
@endsection
