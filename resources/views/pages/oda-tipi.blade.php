@extends('layouts.app')
@php($siteBrand = \App\Models\Setting::al('site_name', config('app.name', 'Pet Otel')))

@section('title', $tip->name . ' Fiyatları | ' . $siteCity . ' | ' . $siteBrand)
@section('desc', $desc)
@section('ogtitle', $siteCity . ' ' . $tip->name . ' Sunan Pet Oteller')
@section('ogdesc', $desc)
@push('head')
{!! sema_ld(sema_bolum('WebPage', [
    'name' => $siteCity . ' ' . $tip->name . ' Sunan Pet Oteller',
    'description' => $desc,
    'url' => url()->current(),
    'inLanguage' => 'tr-TR',
])) !!}
{!! sema_kirinti([
    ['ad' => 'Ana Sayfa', 'url' => route('home')],
    ['ad' => 'Kategoriler', 'url' => route('hub.kategori')],
    ['ad' => $tip->name],
]) !!}
@endpush

@section('content')
<div class="wrap">
  <div class="crumb"><a href="{{ route('home') }}">Ana Sayfa</a> ›
    <a href="{{ route('hub.kategori') }}">Kategoriler</a> › {{ $tip->name }}</div>

  <h1>{{ $siteCity }}’da {{ $tip->name }} Sunan Pet Oteller</h1>
  <p class="giris">{{ $desc }}</p>

  <section class="odabilgi">
    <div class="odabilgi-kutu">
      <span class="lbl">Günlük ücret</span>
      <b>{{ $fiyatMetin }}</b>
    </div>
    <div class="odabilgi-kutu">
      <span class="lbl">Bulunan tesis</span>
      <b>{{ $otelAdedi }}</b>
    </div>
    <div class="odabilgi-kutu">
      <span class="lbl">Oda kapasitesi</span>
      <b>{{ $kapasite }}</b>
    </div>
    @if($turMetin !== '')<div class="odabilgi-kutu">
      <span class="lbl">Kabul edilen</span>
      <b>{{ ucfirst($turMetin) }}</b>
    </div>@endif
    @if($olcuMetin !== '')<div class="odabilgi-kutu">
      <span class="lbl">Uygun ölçü</span>
      <b>{{ ucfirst($olcuMetin) }}</b>
    </div>@endif
  </section>

  <section>
    <h2>{{ $tip->name }} sunan tesisler</h2>
    <div class="pw-otel">
      @foreach($hoteller as $h)
        @include('hotels._card', ['h' => $h])
      @endforeach
    </div>
  </section>

  <section>
    <h2>{{ $tip->name }} hakkında</h2>
    <div class="prose">
      <p>{{ $tip->name }}, {{ $siteCity }}’daki {{ $otelAdedi }} pet otelde bulunan bir oda 
        seçeneğidir. Günlük ücretler oda tipine göre değişmekte olup bu sayfadaki 
        aralık {{ $fiyatMetin }} bandındadır; her tesisin kendi sayfasında oda 
        tipleri ve günlük ücreti ayrı ayrı listelenir.</p>
      @if($turMetin !== '')<p>Bu oda tipi {{ $turMetin }} kabul eden tesislerde bulunur. 
        Dostunuzun türüne göre uygun tesisi seçerken oda tipi bilgisini dikkate alın.</p>@endif
      @if($olcuMetin !== '')<p>Oda ölçüsü açısından {{ $olcuMetin }} için uygundur. 
        Köpeklerde ırk ve boyut, kedilerde yaş ve hareketlilik oda seçimini etkiler.</p>@endif
      <p>Konaklama ücreti işletmeye aittir ve işletmenin onayıyla kesinleşir. 
        Rezervasyon süreci ve iptal koşulları için 
        <a href="{{ route('pages.show', 'nasil-calisir') }}">Nasıl Çalışır?</a> 
        sayfasına bakabilirsiniz.</p>
    </div>
  </section>

  @if($ilceler->isNotEmpty())
  <section>
    <h2>İlçelere göre {{ $tip->name }}</h2>
    <div class="chip-liste">
      @foreach($ilceler as $d)
        <a class="chip" href="{{ url('/' . $d->slug) }}">{{ $d->name }}</a>
      @endforeach
    </div>
  </section>
  @endif

  <section class="cta">
    <h2>Farklı oda tipleri mi arıyorsunuz?</h2>
    <p>Tüm pet otelleri, ilçeler ve oda seçeneklerini karşılaştırın.</p>
    <a class="btn" href="{{ route('hotels.all') }}">Pet otelleri gör</a>
    <a class="btn btn-2" href="{{ route('hub.kategori') }}">Kategorilere dön</a>
  </section>
</div>
@endsection
