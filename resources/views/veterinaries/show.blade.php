@extends('layouts.app')
@php
    $siteBrand = \App\Models\Setting::al('site_name', config('app.name', 'Pet Otel'));
    $siteCity  = \App\Models\Setting::al('site_city', 'Antalya') ?: 'Antalya';
    $ilceAdi   = $vet->district?->name;
    $telHref   = $vet->telHref();
    $gorsel    = $vet->image && !str_contains(strtolower((string) $vet->image), 'img/vet/varsayilan.svg')
        ? (str_starts_with($vet->image, 'http') ? $vet->image : (str_starts_with($vet->image, 'img/') ? asset($vet->image) : asset('storage/' . $vet->image)))
        : null;
    $aciklama = trim(strip_tags((string) $vet->description));
    $otellerUrl = route('hotels.all');
    $gunAdlari = ['monday'=>'Pazartesi','tuesday'=>'Salı','wednesday'=>'Çarşamba','thursday'=>'Perşembe','friday'=>'Cuma','saturday'=>'Cumartesi','sunday'=>'Pazar'];
    $saatler = [];
    foreach ((array) $vet->opening_hours as $gun => $saat) {
        $gunLabel = $gunAdlari[strtolower((string) $gun)] ?? ucfirst((string) $gun);
        if (is_array($saat)) {
            $acilis = $saat['open'] ?? null;
            $kapanis = $saat['close'] ?? null;
            $saatMetni = $acilis ? (in_array(mb_strtolower((string) $acilis), ['24 saat açık','24 saat acik'], true) ? '24 saat açık' : ((string) $acilis . ($kapanis ? '–' . $kapanis : ''))) : 'Bilgi yok';
        } else {
            $saatMetni = trim((string) $saat);
        }
        if ($saatMetni !== '') $saatler[$gunLabel] = $saatMetni;
    }
@endphp
@section('title', $vet->name . ' | ' . ($ilceAdi ?: $siteCity) . ' veteriner kliniği')
@section('desc', seo_metin($aciklama ?: $vet->name . ' için adres ve iletişim bilgilerini inceleyin. Çalışma saatini ve hizmet kapsamını ziyaret öncesinde telefonla doğrulayın.', 158))
@section('canonical', route('vets.show', $vet->slug))
@section('ogtype', 'website')
@section('ogimage', $gorsel)
@push('head')
<link rel="stylesheet" href="{{ asset('css/veterinerler.css') }}?v=20261013b">
{!! sema_ld(array_filter([
    '@context' => 'https://schema.org',
    '@type' => 'VeterinaryCare',
    'name' => $vet->name,
    'url' => route('vets.show', $vet->slug),
    'image' => $gorsel,
    'telephone' => $telHref ? $vet->phone : null,
    'address' => $vet->address ? array_filter(['@type'=>'PostalAddress','streetAddress'=>$vet->address,'addressLocality'=>$ilceAdi,'addressRegion'=>$siteCity,'addressCountry'=>'TR']) : null,
    'areaServed' => $ilceAdi ?: $siteCity,
    'geo' => ($vet->lat && $vet->lng) ? ['@type'=>'GeoCoordinates','latitude'=>$vet->lat,'longitude'=>$vet->lng] : null,
])) !!}
{!! sema_kirinti([['ad'=>'Ana Sayfa','url'=>route('home')],['ad'=>'Veterinerler','url'=>route('vets.index')],['ad'=>$vet->name]]) !!}
@endpush
@section('content')
<main class="wrap vet-detail" id="icerik">
  <nav class="vet-crumb" aria-label="İçerik yolu"><a href="{{ route('home') }}">Ana Sayfa</a><span aria-hidden="true">›</span><a href="{{ route('vets.index') }}">Veterinerler</a>@if($ilceAdi)<span aria-hidden="true">›</span><a href="{{ route('vets.index', ['ilce' => $vet->district?->slug]) }}">{{ $ilceAdi }}</a>@endif<span aria-hidden="true">›</span><span aria-current="page">{{ $vet->name }}</span></nav>

  <section class="vet-profile-hero" aria-labelledby="vet-profile-title">
    <div class="vet-profile-media {{ $gorsel ? '' : 'has-placeholder' }}">@if($gorsel)<img src="{{ $gorsel }}" alt="{{ $vet->name }} kliniği" width="900" height="480" fetchpriority="high">@else<div class="vet-photo-placeholder" role="img" aria-label="{{ $vet->name }} · Klinik fotoğrafı eklenmemiş"><span aria-hidden="true">✚</span><strong>{{ $vet->name }}</strong><small>Klinik fotoğrafı eklenmedi</small></div>@endif</div>
    <div class="vet-profile-copy">
      <span class="vet-eyebrow">{{ $siteCity }} · {{ $ilceAdi ?: 'Veteriner rehberi' }}</span>
      <h1 id="vet-profile-title">{{ $vet->name }}</h1>
      <p class="vet-profile-address">@if($ilceAdi)<span aria-hidden="true">⌖</span>{{ $ilceAdi }}, {{ $siteCity }}@endif</p>
      <div class="vet-profile-badges">@if($vet->is_24h)<span class="vet-badge vet-badge-24">7/24 açık olduğu belirtiliyor</span>@endif<span class="vet-profile-note">Bilgiler değişmiş olabilir; ziyaret öncesinde arayın.</span></div>
      <div class="vet-detail-actions">
        @if($telHref)<a class="vet-button" href="{{ $telHref }}"><span aria-hidden="true">☎</span> Kliniği ara</a>@endif
        @if($vet->whatsapp)<a class="vet-button vet-button-light" href="https://wa.me/{{ preg_replace('/\D+/', '', (string) $vet->whatsapp) }}" target="_blank" rel="nofollow noopener">WhatsApp</a>@endif
        @if($vet->website)<a class="vet-button vet-button-light" href="{{ $vet->website }}" target="_blank" rel="nofollow noopener">Web sitesi <span aria-hidden="true">↗</span></a>@endif
        @if($vet->maps_url && !preg_match('~abc123|def456|ghi789|jkl012|mno345|pqr678~i', $vet->maps_url))<a class="vet-button vet-button-light" href="{{ $vet->maps_url }}" target="_blank" rel="nofollow noopener">Yol tarifi <span aria-hidden="true">↗</span></a>@endif
      </div>
    </div>
  </section>

  <div class="vet-detail-grid">
    <div class="vet-detail-main">
      @if($aciklama)<section class="vet-content-card" aria-labelledby="vet-about-title"><span class="vet-eyebrow">Klinik profili</span><h2 id="vet-about-title">Klinik hakkında</h2><p class="vet-description">{{ $aciklama }}</p></section>@endif

      @if(!empty($vet->services))
      <section class="vet-content-card" aria-labelledby="vet-services-title"><span class="vet-eyebrow">Profilde listelenenler</span><h2 id="vet-services-title">Hizmet bilgileri</h2><p class="vet-section-intro">Aşağıdaki hizmetler klinik profiline eklenmiştir. Güncel kapsamı ve randevu gerekip gerekmediğini kliniği arayarak doğrulayın.</p><ul class="vet-services">@foreach($vet->services as $service)<li><span aria-hidden="true">＋</span>{{ $service }}</li>@endforeach</ul></section>
      @endif

      @if($vet->lat && $vet->lng)
      <section class="vet-content-card" aria-labelledby="vet-location-title"><span class="vet-eyebrow">Adres ve ulaşım</span><h2 id="vet-location-title">Konum</h2>@if($vet->address)<p class="vet-section-intro">{{ $vet->address }}</p>@endif<div class="vet-map"><iframe title="{{ $vet->name }} yaklaşık konum haritası" loading="lazy" referrerpolicy="no-referrer-when-downgrade" src="https://www.openstreetmap.org/export/embed.html?bbox={{ $vet->lng - 0.006 }}%2C{{ $vet->lat - 0.004 }}%2C{{ $vet->lng + 0.006 }}%2C{{ $vet->lat + 0.004 }}&layer=mapnik&marker={{ $vet->lat }}%2C{{ $vet->lng }}"></iframe></div><p class="vet-source">Harita işaretçisi yaklaşık konumu gösterebilir. Yola çıkmadan önce adresi ve tarifi klinikle teyit edin.</p></section>
      @endif
    </div>

    <aside class="vet-detail-side" aria-label="İletişim ve çalışma bilgileri">
      <section class="vet-info-card"><span class="vet-eyebrow">İletişim</span><h2>Adrese ve telefona ulaşın</h2><dl>
        @if($vet->address)<div><dt>Adres</dt><dd>{{ $vet->address }}</dd></div>@endif
        @if($vet->phone)<div><dt>Telefon</dt><dd><a href="{{ $telHref }}">{{ $vet->phone }}</a></dd></div>@endif
        @if($vet->website)<div><dt>Web sitesi</dt><dd><a href="{{ $vet->website }}" target="_blank" rel="nofollow noopener">{{ parse_url($vet->website, PHP_URL_HOST) ?: $vet->website }} <span aria-hidden="true">↗</span></a></dd></div>@endif
      </dl>
      @if($saatler)<h3>Listelenen çalışma saatleri</h3><ul class="vet-hours">@foreach($saatler as $gun => $saat)<li><span>{{ $gun }}</span><b>{{ $saat }}</b></li>@endforeach</ul><p class="vet-source">Saatler değişebilir. Özellikle acil durumda yola çıkmadan önce telefonla teyit edin.</p>@endif
      </section>
      <section class="vet-cta-card"><span class="vet-eyebrow">Antalya Pet Otel</span><h2>Konaklama mı arıyorsunuz?</h2><p>Kediniz veya köpeğiniz için tesisleri, oda bilgilerini ve gecelik ücretleri karşılaştırın.</p><a class="vet-button vet-button-light" href="{{ $otellerUrl }}">Pet otellerini incele <span aria-hidden="true">→</span></a></section>
    </aside>
  </div>

  @if($benzer->isNotEmpty())
  <section class="vet-related" aria-labelledby="vet-related-title"><div class="vet-results-head"><div><span class="vet-eyebrow">Yakın çevre</span><h2 id="vet-related-title">{{ $ilceAdi ?: $siteCity }} bölgesindeki diğer klinikler</h2></div><a class="vet-text-link" href="{{ route('vets.index', array_filter(['ilce' => $vet->district?->slug])) }}">Tümünü gör <span aria-hidden="true">→</span></a></div><div class="vet-grid vet-grid-sm">@foreach($benzer as $b)<article class="vet-related-card"><div><h3><a href="{{ route('vets.show', $b->slug) }}">{{ $b->name }}</a></h3>@if($b->district)<p>{{ $b->district->name }}</p>@endif</div>@if($b->phone && $b->telHref())<a class="vet-card-call" href="{{ $b->telHref() }}" aria-label="{{ $b->name }} kliniğini ara">Ara</a>@endif</article>@endforeach</div></section>
  @endif
</main>
@endsection
