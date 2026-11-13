@extends('layouts.app')


@section('title', 'Sayfa bulunamadı | Antalya Pet Otel')
@section('desc', 'Aradığınız sayfa taşınmış veya hiç var olmamış olabilir. Antalya pet otelleri, ilçeler, fiyat rehberi ve yardım sayfalarına buradan ulaşabilirsiniz.')
@section('robots', 'noindex, follow')

@section('content')
@php
    /* 404 icin gereken veri. @php blogu @section DISINDA olursa degiskenler
       layout'a tasinmaz ve "Undefined variable $ilceler" hatasi verir (log'da 229 kez);
       bu yuzden section icinde tanimlanir. */
    $ilceler = collect();
    $hoteller = collect();
    try {
        $hv = app(\App\Http\Controllers\PageController::class)->hataVeri();
        $ilceler = $hv['ilceler'];
        $hoteller = $hv['hoteller'];
    } catch (\Throwable $e) {
        // Veritabanı erişilemiyorsa 404 sayfası öneri listeleri olmadan da açılmalı.
    }
@endphp
<div class="wrap">
  <div class="crumb"><a href="{{ route('home') }}">Ana Sayfa</a> › 404</div>

  <section class="hata-sayfa">
    <div class="hata-kod" aria-hidden="true">404</div>
    <h1>Aradığınız sayfayı bulamadık</h1>
    <p>Bağlantı taşınmış, adı değişmiş ya da hiç var olmamış olabilir. Aşağıdaki başlıklardan devam edebilirsiniz.</p>

    <form class="pw-search hata-ara" action="{{ route('search') }}" method="get" role="search">
      <div class="sf">
        <label for="e404q" class="sr-only">Otel ara</label>
        <span class="ic" aria-hidden="true">&#128269;</span>
        <input id="e404q" type="search" name="q" placeholder="Otel, ilçe veya konum ara" value="{{ request('q') }}">
      </div>
      <button class="btn" type="submit">Ara</button>
    </form>

    <div class="hata-baglantilar">
      <a class="btn" href="{{ route('home') }}">Ana sayfa</a>
      <a class="btn btn-2" href="{{ route('hotels.all') }}">Tüm pet otelleri</a>
      <a class="btn btn-2" href="{{ route('fiyatlar') }}">Fiyat rehberi</a>
      <a class="btn btn-2" href="{{ url('/yardim-merkezi') }}">Yardım merkezi</a>
      <a class="btn btn-2" href="{{ url('/site-haritasi') }}">Site haritası</a>
    </div>
  </section>

  @if($ilceler->isNotEmpty())
  <section>
    <h2>Popüler ilçeler</h2>
    <div class="chip-liste">
      @foreach($ilceler as $d)
        <a class="chip" href="{{ url('/' . $d->slug) }}">{{ $d->name }}</a>
      @endforeach
    </div>
  </section>
  @endif

  @if($hoteller->isNotEmpty())
  <section>
    <h2>Bazı pet otelleri</h2>
    <div class="pw-otel">
      @foreach($hoteller as $h)
        @include('hotels._card', ['h' => $h])
      @endforeach
    </div>
  </section>
  @endif
</div>
@endsection