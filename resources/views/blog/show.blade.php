@extends('layouts.app')
@php
    $govde   = (string) $post->body;
    $baslik  = (string) $post->title;
    $siteBrand = \App\Models\Setting::al('site_name', config('app.name', 'Pet Otel'));
    $dTarih  = $post->published_at ? $post->published_at->format('d.m.Y') : '';
    $dSaat   = $post->published_at ? date('H:i', strtotime((string) $post->published_at)) : '';
    $kelime  = str_word_count(strip_tags($govde));
    $sure    = max(1, (int) round($kelime / 220));

    /* Icindekiler: govdedeki H2/H3 basliklari */
    $toc = [];
    if (preg_match_all('~<h([23])[^>]*>(.*?)</h\1>~is', $govde, $m, PREG_SET_ORDER)) {
        foreach ($m as $b) {
            $metin = trim(preg_replace('~\s+~u', ' ', strip_tags($b[2])) ?? '');
            if ($metin === '') continue;
            $baseId = seo_slug($metin) ?: 'bolum';
            $id = 'bolum-' . $baseId;
            $suffix = 2;
            while (in_array($id, array_column($toc, 'id'), true)) $id = 'bolum-' . $baseId . '-' . $suffix++;
            $toc[] = ['seviye' => (int) $b[1], 'metin' => $metin, 'id' => $id];
            if (count($toc) >= 12) break;
        }
    }

    $tocIndex = 0;
    $govde = preg_replace_callback('~<h([23])([^>]*)>(.*?)</h\\1>~is', function ($heading) use (&$toc, &$tocIndex) {
        $item = $toc[$tocIndex++] ?? null;
        if (!$item) return $heading[0];
        $attrs = $heading[2];
        return '<h' . $heading[1] . $attrs . ' id="' . $item['id'] . '">' . $heading[3] . '</h' . $heading[1] . '>';
    }, $govde) ?? $govde;

    $kirinti = [
        ['ad' => 'Ana Sayfa', 'url' => route('home')],
        ['ad' => 'Blog', 'url' => route('blog.index')],
        ['ad' => seo_metin($baslik, 60, false)],
    ];
@endphp
@php
    /* Yaziya ozgu kapak gorseli (Google Article icin zorunlu).
     * Seo.php ayni dosyayi semada da kullaniyor; burada og:image
     * icin. Once section bos oldugu icin her yazi ayni
     * og-default.png gosteriyordu. */
    $yaziGor = blog_cover_asset((string) $post->slug, $baslik);
    $sss = function_exists('sayfa_sss_cikar') ? sayfa_sss_cikar($govde) : [];
@endphp
@section('ogimage', $yaziGor)
@section('title', $baslik . ' | ' . $siteBrand . ' Blog')
@section('desc', seo_metin($post->excerpt ?: strip_tags($govde), 158))
@section('ogtitle', $baslik)
@section('ogdesc', seo_metin($post->excerpt ?: strip_tags($govde), 200))
@push('head')
<meta property="article:published_time" content="{{ $post->published_at?->toAtomString() }}">
<meta property="article:modified_time" content="{{ $post->updated_at?->toAtomString() }}">
<meta property="article:section" content="Pet Otel">
{!! seo_yazi_sema($post, $kirinti) !!}
@if(count($sss) >= 3){!! sema_sss($sss) !!}@endif
@endpush
@section('content')
<div class="wrap blog-detail">
<div class="crumb"><a href="{{ route('home') }}">Ana Sayfa</a> ›
  <a href="{{ route('blog.index') }}">Blog</a> › {{ seo_metin($baslik, 60, false) }}</div>

<header class="blog-detail-hero"><img src="{{ $yaziGor }}" alt="{{ $baslik }} rehber görseli" width="1200" height="630" fetchpriority="high" decoding="async" style="display:block;width:100%;aspect-ratio:16/8;object-fit:cover;border-radius:16px;margin:0 0 20px"><span class="blog-eyebrow">{{ $siteBrand }} · Rehber</span><h1 class="blog-article-title">{{ $baslik }}</h1>
<p class="meta blog-post-meta">
  @if($dTarih)<span>&#128197; {{ $dTarih }}@if($dSaat) · {{ $dSaat }}@endif</span>@endif
  <span>&#128337; {{ $sure }} dk okuma</span>
  <span>Yayınlayan: {{ $siteBrand }}</span>
  @if($post->updated_at && $post->published_at && $post->updated_at->gt($post->published_at))
    <span>&#128260; Güncellendi {{ $post->updated_at->format('d.m.Y') }}</span>@endif
</p></header>

<div class="blog-reading-layout">
@if(count($toc) >= 3)
<aside class="blog-toc-card" aria-label="Yazı içeriği"><span class="blog-reading-label">YAZI İÇERİĞİ</span><b>İçindekiler</b><nav><ol>@foreach($toc as $t)<li class="{{ $t['seviye'] === 3 ? 'is-subsection' : '' }}"><a href="#{{ $t['id'] }}">{{ $t['metin'] }}</a></li>@endforeach</ol></nav><a class="blog-back-link" href="{{ route('blog.index') }}">← Tüm rehberler</a></aside>
@endif
<article class="prose blog-article">{!! makale_guvenli_html($govde, $baslik) !!}</article>
<p class="hint">Bu yazı genel bilgilendirme amaçlıdır; veteriner hekim muayenesinin ve tedavisinin yerine geçmez. Evcil hayvanınızın sağlığıyla ilgili kararlar için mutlaka bir veteriner hekime danışın.</p>
</div>

<div class="box blog-editorial" style="margin:22px 0">
  <p class="hint" style="margin:0 0 6px"><b>{{ $siteBrand }} içerik ekibi tarafından hazırlandı{{ $post->updated_at ? ' ve son olarak ' . $post->updated_at->format('d.m.Y') . ' tarihinde gözden geçirildi' : '' }}.</b></p>
  <p class="hint" style="margin:0">Bu içerik genel bilgilendirme amaçlıdır; sağlık, davranış veya resmi işlemlerle ilgili konularda veteriner hekiminize ya da yetkili kuruma danışmanızı öneririz.</p>
</div>

@if($ilceler->isNotEmpty())
<div class="box" style="margin:24px 0">
  <b>Yazıda geçen ilçelerdeki pet oteller</b>
  <div class="chips" style="margin-top:8px">
    @foreach($ilceler as $il)<a class="chip" href="{{ url('/' . $il->slug) }}">{{ $il->name }} Pet Otel</a>@endforeach
  </div>
</div>
@endif

@if(!empty($yazilar))
<h2>İlgili yazılar</h2>
<div class="grid">
@foreach($yazilar as $y)
  <a class="card" href="{{ route('blog.show', $y->slug) }}">
    <div class="thumb">
      <span aria-hidden="true">&#128054;</span>
      <span class="kind">Rehber</span>
    </div>
    <div class="cb">
      <h3>{{ $y->title }}</h3>
      <p class="hint" style="margin:0;font-size:14px">{{ seo_metin($y->excerpt ?: strip_tags((string) $y->body), 95) }}</p>
    </div>
  </a>
@endforeach
</div>
@endif

@if($onceki || $sonraki)
<div class="row2" style="margin:22px 0 40px">
  <div>@if($sonraki)<a href="{{ route('blog.show', $sonraki->slug) }}"><small>Sonraki yazı</small><br>{{ seo_metin($sonraki->title, 70, false) }}</a>@endif</div>
  <div style="text-align:right">@if($onceki)<a href="{{ route('blog.show', $onceki->slug) }}"><small>Önceki yazı</small><br>{{ seo_metin($onceki->title, 70, false) }}</a>@endif</div>
</div>
@endif
</div>
@endsection
