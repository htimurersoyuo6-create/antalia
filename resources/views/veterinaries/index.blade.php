@extends('layouts.app')
@php
    $siteBrand = \App\Models\Setting::al('site_name', config('app.name', 'Pet Otel'));
    $siteCity  = \App\Models\Setting::al('site_city', 'Antalya') ?: 'Antalya';
    $aktifIlce = $ilce !== '' ? (optional($ilceler->firstWhere('slug', $ilce))->name ?? '') : '';
    $h1 = $aktifIlce ? $aktifIlce . ' veteriner klinikleri' : $siteCity . ' veteriner klinikleri';
    $giris = $aktifIlce
        ? $aktifIlce . ' bölgesindeki veteriner kliniklerinin adres ve iletişim bilgilerini inceleyin. Çalışma saatlerini, hizmetleri ve acil kabul durumunu gitmeden önce klinikle doğrulayın.'
        : $siteCity . ' genelindeki veteriner kliniklerini ilçe, ad veya adres bilgisine göre bulun. Ziyaret öncesinde çalışma saatini ve ihtiyacınız olan hizmeti telefonla teyit edin.';
    $sayfaNo = max(1, (int) request('page', 1));
    $canonical = route('vets.index', array_filter(['ilce' => $ilce ?: null]));
@endphp
@section('title', $h1 . ' | ' . $siteBrand)
@section('desc', seo_metin($giris, 158))
@section('canonical', $canonical)
@section('robots', 'index, follow, max-image-preview:large, max-snippet:-1')
@push('head')
<link rel="stylesheet" href="{{ asset('css/veterinerler.css') }}?v=20261013b">
{!! seo_liste_sema($h1, $canonical, seo_metin($giris, 250, false), $vets->map(fn ($v) => ['@type' => 'VeterinaryCare', 'name' => $v->name, 'url' => route('vets.show', $v->slug)])->all()) !!}
{!! sema_kirinti(array_merge([['ad' => 'Ana Sayfa', 'url' => route('home')], ['ad' => 'Veterinerler', 'url' => route('vets.index')]], $aktifIlce ? [['ad' => $aktifIlce]] : [])) !!}
@endpush
@section('content')
<main class="wrap vet-index" id="icerik">
  <nav class="vet-crumb" aria-label="İçerik yolu"><a href="{{ route('home') }}">Ana Sayfa</a><span aria-hidden="true">›</span><span aria-current="page">Veterinerler</span>@if($aktifIlce)<span aria-hidden="true">›</span><span aria-current="page">{{ $aktifIlce }}</span>@endif</nav>

  <section class="vet-hero" aria-labelledby="vet-page-title">
    <div class="vet-hero-copy">
      <span class="vet-eyebrow">{{ $siteCity }} · Veteriner rehberi</span>
      <h1 id="vet-page-title">{{ $h1 }}</h1>
      <p>{{ $giris }}</p>
    </div>
    <form class="vet-search" method="get" action="{{ route('vets.index') }}" role="search" aria-label="Veteriner kliniği ara">
      <label class="vet-search-field"><span class="sr-only">Klinik adı veya adres</span><input type="search" name="q" value="{{ $q }}" placeholder="Klinik, mahalle veya adres ara…"></label>
      @if($ilce !== '')<input type="hidden" name="ilce" value="{{ $ilce }}">@endif
      <button class="vet-button" type="submit">Klinik ara <span aria-hidden="true">→</span></button>
    </form>
  </section>

  @if($ilceler->isNotEmpty())
  <nav class="vet-filters" aria-label="İlçeye göre filtrele">
    <a class="vet-filter {{ $ilce === '' ? 'is-active' : '' }}" href="{{ route('vets.index', array_filter(['q' => $q ?: null])) }}">Tüm ilçeler</a>
    @foreach($ilceler as $il)
      <a class="vet-filter {{ $ilce === $il->slug ? 'is-active' : '' }}" href="{{ route('vets.index', array_filter(['ilce' => $il->slug, 'q' => $q ?: null])) }}">{{ $il->name }}</a>
    @endforeach
  </nav>
  @endif

  <section class="vet-results" aria-labelledby="vet-results-title">
    <div class="vet-results-head"><div><span class="vet-eyebrow">Arama sonuçları</span><h2 id="vet-results-title">{{ $aktifIlce ?: $siteCity }} veterinerleri</h2></div><p class="vet-count"><strong>{{ $vets->total() }}</strong> klinik kaydı</p></div>
    <div class="vet-grid">
      @forelse($vets as $v)
      @php
        $gorsel = $v->image && !str_contains(strtolower((string) $v->image), 'img/vet/varsayilan.svg')
          ? (str_starts_with($v->image, 'http') ? $v->image : (str_starts_with($v->image, 'img/') ? asset($v->image) : asset('storage/' . $v->image)))
          : null;
      @endphp
      <article class="vet-card">
        <a class="vet-card-main" href="{{ route('vets.show', $v->slug) }}" aria-label="{{ $v->name }} kliniğinin bilgilerini incele">
          <div class="vet-card-photo {{ $gorsel ? '' : 'has-placeholder' }}">@if($gorsel)<img src="{{ $gorsel }}" alt="{{ $v->name }} kliniği" loading="lazy" decoding="async" width="600" height="360">@else<div class="vet-photo-placeholder" role="img" aria-label="{{ $v->name }} · Klinik fotoğrafı eklenmemiş"><span aria-hidden="true">✚</span><strong>{{ $v->name }}</strong><small>Klinik fotoğrafı eklenmedi</small></div>@endif @if($v->is_24h)<span class="vet-badge vet-badge-24">7/24 açık</span>@endif</div>
          <div class="vet-card-body">
            <span class="vet-card-kicker">Veteriner kliniği</span>
            <h3>{{ $v->name }}</h3>
            @if($v->district)<p class="vet-card-place"><span aria-hidden="true">⌖</span>{{ $v->district->name }}{{ $siteCity ? ', ' . $siteCity : '' }}</p>@endif
            @if($v->address)<p class="vet-card-addr">{{ seo_metin($v->address, 100) }}</p>@endif
          </div>
        </a>
        <div class="vet-card-actions">
          <a class="vet-card-detail" href="{{ route('vets.show', $v->slug) }}">Klinik bilgileri <span aria-hidden="true">→</span></a>
          @if($v->phone && $v->telHref())<a class="vet-card-call" href="{{ $v->telHref() }}" aria-label="{{ $v->name }} kliniğini ara">Ara</a>@endif
        </div>
      </article>
      @empty
      <div class="vet-empty"><span class="vet-empty-icon" aria-hidden="true">⌕</span><h3>Bu aramayla eşleşen klinik bulunamadı</h3><p>Arama sözcüğünü kısaltabilir veya başka bir ilçe seçebilirsiniz.</p><a class="vet-button vet-button-light" href="{{ route('vets.index') }}">Tüm klinikleri göster</a></div>
      @endforelse
    </div>
    @if($vets->hasPages())<div class="vet-pager">{{ $vets->onEachSide(1)->links('vendor.pagination.blog') }}</div>@endif
  </section>

  <section class="vet-guide" aria-labelledby="vet-guide-title">
    <div class="vet-guide-heading"><span class="vet-eyebrow">Ziyaret öncesi kısa kontrol listesi</span><h2 id="vet-guide-title">Veteriner kliniği seçerken nelere bakmalı?</h2><p>Her kliniğin hizmet kapsamı ve çalışma düzeni farklı olabilir. İhtiyacınızı anlatıp bilgileri doğrudan klinikten doğrulayın.</p></div>
    <div class="vet-guide-grid">
      <article><span>01</span><div><h3>İhtiyacınızı netleştirin</h3><p>Rutin muayene, aşı, görüntüleme veya acil durum için hangi hizmetin gerektiğini belirtin.</p></div></article>
      <article><span>02</span><div><h3>Gitmeden önce arayın</h3><p>Çalışma saatini, randevu gerekip gerekmediğini ve kliniğin o sırada hasta kabul edip etmediğini sorun.</p></div></article>
      <article><span>03</span><div><h3>Acil durumu teyit edin</h3><p>“7/24” bilgisi her yerde güncel olmayabilir. Gece veya tatil günü yola çıkmadan önce mutlaka telefon edin.</p></div></article>
    </div>
    <p class="vet-disclaimer">Bu rehber iletişim ve konum bilgilerini bulmayı kolaylaştırır; veteriner hekim önerisi veya acil sağlık hizmeti yerine geçmez. Listelenen bilgilerin güncelliğini klinikle teyit edin.</p>
  </section>
</main>
@endsection
