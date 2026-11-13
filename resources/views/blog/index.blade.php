@extends('layouts.app')
@php
    $siteBrand = \App\Models\Setting::al('site_name', config('app.name', 'Pet Otel'));
    $siteCity = \App\Models\Setting::al('site_city', '');
    $baslik = $tur ? ($kategoriler[$tur] ?? 'Blog') . ' Konuları' : 'Pet Otel Rehberi';
    $sayfaNo = max(1, (int) request('page', 1));
    $blogCanonical = $sayfaNo > 1 && !$tur
        ? route('blog.index', ['page' => $sayfaNo])
        : route('blog.index');
    $sayfaBaslik = $sayfaNo > 1 ? $baslik . ' – Sayfa ' . $sayfaNo : $baslik;
    $giris  = ($siteCity !== '' ? $siteCity . ' ve çevresinde' : 'Kedi ve köpek')
        . ' konaklaması, oda seçimi, bakım ve rezervasyon süreci hakkında yayımlanan rehber yazılar.';
@endphp
@section('title', $sayfaBaslik . ' | ' . $siteBrand . ' Blog')
@section('desc', seo_metin($giris, 158))
@section('ogtitle', $baslik)
@section('canonical', $blogCanonical)
@section('robots', $sayfaNo > 1 && !$tur ? 'index, follow, max-image-preview:large, max-snippet:-1' : ($tur ? 'noindex, follow' : 'index, follow, max-image-preview:large, max-snippet:-1'))
@push('head')
{!! sema_ld(sema_bolum('Blog', [
    'name' => $baslik,
    'url' => $blogCanonical,
    'description' => seo_metin($giris, 250, false),
    'inLanguage' => 'tr-TR',
    'blogPost' => array_map(
        static fn($p) => [
            '@type' => 'BlogPosting',
            'headline' => $p->title,
            'url' => route('blog.show', $p->slug),
            'datePublished' => seo_zaman($p->published_at),
        ],
        $posts->items()
    ),
])) !!}
{!! sema_kirinti([['ad' => 'Ana Sayfa', 'url' => route('home')], ['ad' => 'Blog']]) !!}
@endpush
@section('content')
<div class="wrap blog-index">
<div class="crumb"><a href="{{ route('home') }}">Ana Sayfa</a> › Blog</div>

<section class="blog-hero"><div class="blog-hero-copy"><span class="blog-eyebrow">{{ $siteBrand }} · Rehberler</span><h1>{{ $baslik }}</h1>
<article class="prose blog-intro" style="max-width:820px">{!! makale_guvenli_html('<p>' . e($giris) . '</p>') !!}</article><div class="blog-hero-tags"><span>🐾 Kedi ve köpek bakımı</span><span>✓ Uygulanabilir bilgiler</span></div></div><div class="blog-hero-mark" aria-hidden="true">🐾</div></section>

@if($posts->count() && (int) request('page', 1) === 1)
  @php($featured = $posts->first())
  <a class="blog-featured" href="{{ route('blog.show', $featured->slug) }}">
    <div class="blog-featured-art">
      <img src="{{ blog_cover_asset($featured->slug) }}" alt="{{ $featured->title }}" width="900" height="560" fetchpriority="high">
      <span class="blog-featured-label">Editörün seçimi</span>
    </div>
    <div class="blog-featured-copy"><span class="blog-eyebrow">ÖNE ÇIKAN REHBER</span><h2>{{ $featured->title }}</h2><p>{{ seo_metin($featured->excerpt ?: strip_tags((string) $featured->body), 190) }}</p><span class="blog-featured-cta">Rehberi oku <b aria-hidden="true">→</b></span></div>
  </a>
@endif

<div class="chips blog-chips">
  @foreach($kategoriler as $k => $ad)
    <a class="chip {{ $tur === $k ? 'on' : '' }}"
       href="{{ $k === '' ? route('blog.index') : route('blog.index') . '?tur=' . $k }}">{{ $ad }}</a>
  @endforeach
</div>

@foreach($konular as $kum)
  @if(!empty($kum['yazilar']))
    <section class="blog-topic">
      <div class="blog-topic-head"><h2>{{ $kum['ad'] }}</h2><span>{{ count($kum['yazilar']) }} rehber</span></div>
      <div class="blog-topic-links">
        @foreach($kum['yazilar'] as $y)
          <a class="blog-topic-link" href="{{ route('blog.show', $y->slug) }}"><strong>{{ $y->title }}</strong><small>{{ seo_metin($y->excerpt ?: strip_tags((string) $y->body), 105) }}</small><span aria-hidden="true">↗</span></a>
        @endforeach
      </div>
    </section>
  @endif
@endforeach

<div class="blog-list-head"><div><span class="blog-eyebrow">Tüm içerikler</span><h2>Tüm yazılar</h2></div><span>{{ $posts->total() }} rehber</span></div>

<div class="grid blog-post-grid">@forelse($posts as $p)
  @if(!((int) request('page', 1) === 1 && $loop->first))
  <a class="card" href="{{ route('blog.show', $p->slug) }}">
    <div class="thumb">
      <img src="{{ blog_cover_asset($p->slug) }}" alt="{{ $p->title }}" fetchpriority="low" decoding="async" width="600" height="315">
      <span class="kind">Rehber</span>
    </div>
    <div class="cb">
      <h3>{{ $p->title }}</h3>
      <div class="meta">
        <span>Pet Otel Rehberi</span>
        @if($p->published_at)<span>{{ $p->published_at->format('d.m.Y') }}</span>@endif
      </div>
      <p class="hint" style="margin:0;font-size:15px">{{ seo_metin($p->excerpt ?: strip_tags((string) $p->body), 110) }}</p>
    </div>
  </a>
  @endif
@empty
  <p>Bu kategoride henüz yazı yok.</p>
@endforelse</div>

@if($posts->hasPages())<div style="margin:26px 0">{{ $posts->onEachSide(1)->links('vendor.pagination.blog') }}</div>@endif

@if($ilceler->isNotEmpty())
<h2>Antalya ilçelerinde pet otel</h2>
<div class="chips">@foreach($ilceler as $il)<a class="chip" href="{{ url('/' . $il->slug) }}">{{ $il->name }}</a>@endforeach</div>
@endif
</div>
@endsection
