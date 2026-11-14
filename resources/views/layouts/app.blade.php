<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
@php
    $siteBrand = \App\Models\Setting::al('site_name', config('app.name', 'Pet Otel'));
    $siteLogo = \App\Models\Setting::al('site_logo', '');
    $siteLogoText = \App\Models\Setting::al('logo_text', '');
    $siteCity = \App\Models\Setting::al('site_city', '') ?: 'Antalya';
    $siteDistrict = \App\Models\Setting::al('site_district', '');
    $sitePlace = implode(', ', array_filter([$siteDistrict, $siteCity]));
    $siteCitySlug = \Illuminate\Support\Str::slug($siteCity ?: 'antalya');
    $defaultMetaTitle = $siteBrand . ' | Kedi ve Köpek Konaklama Rehberi';
    $defaultMetaDescription = ($sitePlace ?: $siteBrand) . ' bölgesindeki kedi ve köpek otellerini, konaklama seçeneklerini ve fiyatları karşılaştırın.';
    $headerMenuDefault = ($siteCity ?: $siteBrand) . ' Pet Otel|/kedi-kopek-otelleri' . "\nKedi Oteli|/kedi-otelleri\nKöpek Oteli|/kopek-otelleri\nFiyatlar|/fiyatlar\nMağaza|/magaza\nBlog|/blog\nKayıp İlanları|/kayip-patiler\nSahiplendir|/sahiplendir\nForum|/forum\nPet Akışı|/pet-akisi\nOtelini Kaydet|/partner";
    $footerExploreDefault = ($siteCity ?: $siteBrand) . ' Pet Otelleri|/kedi-kopek-otelleri' . "\nKöpek Otelleri|/kopek-otelleri\nKedi Otelleri|/kedi-otelleri\nBlog|/blog\nKayıp İlanları|/kayip-patiler\nSahiplendir|/sahiplendir\nForum|/forum\nPet Akışı|/pet-akisi\nGünlük Fiyatlar|/fiyatlar";
    $analyticsId = config('services.google.analytics_measurement_id');
    $analyticsId = is_string($analyticsId) && preg_match('/\AG-[A-Z0-9]{4,20}\z/D', $analyticsId) ? $analyticsId : null;
    $googleVerification = config('services.google.site_verification');
    $analyticsPageAllowed = !auth()->check() && !request()->routeIs('panel.*', 'admin.*');
    $analyticsConsent = request()->cookie('analytics_consent');
@endphp
<title>@yield('title', \App\Models\Setting::al('meta_title', $defaultMetaTitle))</title>
<meta name="description" content="@yield('desc', \App\Models\Setting::al('meta_description', $defaultMetaDescription))">
@if(is_string($googleVerification) && trim($googleVerification) !== '')
<meta name="google-site-verification" content="{{ trim($googleVerification) }}">
@endif
@if($analyticsId && $analyticsPageAllowed && $analyticsConsent === 'accepted')
<script async src="https://www.googletagmanager.com/gtag/js?id={{ urlencode($analyticsId) }}"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  window.gtag = window.gtag || function(){window.dataLayer.push(arguments);};
  window.gtag('js', new Date());
  window.gtag('config', @json($analyticsId), {allow_google_signals:false,allow_ad_personalization_signals:false});
</script>
@endif
@php
    /* Canonical ve robots: izleme parametrelerinden arindirilmis.
     *
     * utm_source / gclid / cb gibi parametrelerle gelen istekler ayni
     * sayfayi farkli adres gibi gosterir; Google her birini ayri sayfa
     * gorup indekslemeye calisir.
     *
     *   - canonical daima yol (sorgu dizesiz) olur
     *   - izleme ya da facet parametresi varsa noindex, follow
     *   - sorgu dizesinin ANLAMLI oldugu sayfalar (kategori filtresi,
     *     blog sayfalama) @section ile acikca bildirir
     */
    $canonicalYol = (string) parse_url((string) url()->current(), PHP_URL_PATH);
    $canonicalUrl = $canonicalYol === '' ? rtrim((string) url('/'), '/') : url($canonicalYol);
    $izlemeParam = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term',
                    'utm_content', 'gclid', 'fbclid', 'cb', 'ref'];
    $facetParam  = ['page', 'fiyat', 'min_fiyat', 'max_fiyat', 'ilce', 'giris', 'cikis', 'tur', 'q'];
    $temiz = !array_intersect($izlemeParam, array_keys($_GET))
          && !array_intersect($facetParam, array_keys($_GET));
$aramaDiziniDisi = auth()->check()
          || request()->routeIs('login', 'register', 'password.*', 'social.*', 'register.phone.verify')
          || request()->is('sifre-yenile/*');
@endphp
<link rel="canonical" href="@yield('canonical', $canonicalUrl)">
<meta name="robots" content="@yield('robots', $temiz && !$aramaDiziniDisi ? 'index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1' : 'noindex, follow')">

<meta property="og:site_name" content="{{ $siteBrand }}">
<meta property="og:locale" content="tr_TR">
<meta property="og:type" content="@yield('ogtype', 'website')">
<meta property="og:title" content="@yield('ogtitle', \App\Models\Setting::al('meta_title', $defaultMetaTitle))">
<meta property="og:description" content="@yield('ogdesc', \App\Models\Setting::al('meta_description', $defaultMetaDescription))">
<meta property="og:url" content="@yield('canonical', $canonicalUrl)">
<meta property="og:image" content="@yield('ogimage', asset('img/og-default.png'))">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta property="og:image:alt" content="@yield('ogtitle', \App\Models\Setting::al('meta_title', $defaultMetaTitle))">

<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="@yield('ogtitle', \App\Models\Setting::al('meta_title', $defaultMetaTitle))">
<meta name="twitter:description" content="@yield('ogdesc', \App\Models\Setting::al('meta_description', $defaultMetaDescription))">
<meta name="twitter:image" content="@yield('ogimage', asset('img/og-default.png'))">

<meta name="theme-color" content="#176447">
<meta name="geo.region" content="TR">
<meta name="geo.placename" content="{{ $sitePlace }}">

<link rel="icon" href="{{ $siteLogo !== '' ? asset('storage/'.$siteLogo) : asset('img/paw-placeholder.svg') }}">
<link rel="apple-touch-icon" href="{{ $siteLogo !== '' ? asset('storage/'.$siteLogo) : asset('img/paw-placeholder.svg') }}">
<link rel="manifest" href="{{ url('/site.webmanifest') }}">

<link rel="stylesheet" href="{{ asset('css/site.v20261011.css') }}?v={{ @filemtime(public_path('css/site.v20261011.css')) }}">
<link rel="stylesheet" href="{{ asset('css/home-search-refine.css') }}?v={{ @filemtime(public_path('css/home-search-refine.css')) }}">
<link rel="stylesheet" href="{{ asset('css/hotel-room-layout-fix.css') }}?v={{ @filemtime(public_path('css/hotel-room-layout-fix.css')) }}">
<link rel="stylesheet" href="{{ asset('css/hotel-detail-refine.v20261016.css') }}?v={{ @filemtime(public_path('css/hotel-detail-refine.v20261016.css')) }}">
@if(auth()->check() && auth()->user()->isOwner() && request()->routeIs('panel.*'))
<link rel="stylesheet" href="{{ asset('css/panel-readability.css') }}?v={{ @filemtime(public_path('css/panel-readability.css')) }}">
@endif
<link rel="stylesheet" href="{{ asset('css/footer-compact-fix.css') }}?v=20261008a">
<link rel="stylesheet" href="{{ asset('css/footer-pet-sites.css') }}?v=20261013a">
<link rel="stylesheet" href="{{ asset("css/site-ux-refine.css") }}?v={{ @filemtime(public_path("css/site-ux-refine.css")) }}">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&display=swap">
<link rel="stylesheet" href="{{ asset('css/patibul-layout.css') }}?v={{ @filemtime(public_path('css/patibul-layout.css')) }}">
<link rel="stylesheet" href="{{ asset('css/patibul-theme.css') }}?v={{ @filemtime(public_path('css/patibul-theme.css')) }}">
<link rel="stylesheet" href="{{ asset('css/site-redesign.css') }}?v={{ @filemtime(public_path('css/site-redesign.css')) }}">
<link rel="stylesheet" href="{{ asset('css/site-redesign-fixes.css') }}?v={{ @filemtime(public_path('css/site-redesign-fixes.css')) }}">
{!! seo_kurum([
    'ad' => $siteBrand,
    'url' => config('app.url'),
    'aciklama' => \App\Models\Setting::al('meta_description', $defaultMetaDescription),
    'adres' => \App\Models\Setting::al('site_address', ''),
    'ilce' => $siteDistrict,
    'sehir' => $siteCity,
    'posta' => '',
    'telefon' => \App\Models\Setting::al('site_phone', ''),
    'tel_e164' => \App\Models\Setting::al('site_phone') ? \App\Models\Setting::telE164() : '',
    'kategori' => 'Yatılı Evcil Hayvan Hizmetleri',
    'logo' => $siteLogo !== '' ? asset('storage/'.$siteLogo) : '',
    'eposta' => \App\Models\Setting::al('site_email', ''),
]) !!}
@stack('head')
</head>
<body class="{{ auth()->check() && auth()->user()->isOwner() && request()->routeIs('panel.*') ? 'has-owner-sidebar ' : '' }}@yield('body_class')">
<a class="skip" href="#icerik">İçeriğe geç</a>
@php
$siteLinks = static function ($value) { return collect(preg_split('/\r?\n/', (string)$value))->map(function($line){ $parts=explode('|',trim($line),2); if(count($parts)!==2)return null; [$label,$path]=$parts; $path=trim($path); if($label==='' || str_starts_with($path,'//') || str_starts_with($path,'\\'))return null; if(str_starts_with($path,'/')) $url=url($path); elseif(preg_match('~^https://[^\s]+$~i',$path)) $url=$path; else return null; return ['label'=>trim($label),'url'=>$url]; })->filter()->values(); };
$headerLinks = $siteLinks(\App\Models\Setting::al('header_menu', $headerMenuDefault));
if (auth()->check()) {
    $headerLinks = $headerLinks->reject(fn ($link) => mb_strtolower($link['label'], 'UTF-8') === mb_strtolower('Otelini Kaydet', 'UTF-8'));
}
$footerExplore = $siteLinks(\App\Models\Setting::al('footer_explore', $footerExploreDefault));
$footerCorporate = $siteLinks(\App\Models\Setting::al('footer_corporate', "Hakkımızda|/sayfa/hakkimizda\nNasıl Çalışır?|/sayfa/nasil-calisir\nSıkça Sorulan Sorular|/sayfa/sss\nOtel Sahibi Ol|/partner\nİletişim|/iletisim"));
if (auth()->check()) {
    $footerCorporate = $footerCorporate->reject(fn ($link) => mb_strtolower($link['label'], 'UTF-8') === mb_strtolower('Otel Sahibi Ol', 'UTF-8'));
}
$footerLegal = $siteLinks(\App\Models\Setting::al('footer_legal', "Gizlilik Politikası|/sayfa/gizlilik\nKullanım Koşulları|/sayfa/kullanim-kosullari\nİptal ve İade|/sayfa/iptal-iade\nMesafeli Satış Politikası|/sayfa/mesafeli-satis-politikasi\nÇerez Politikası|/sayfa/cerez-politikasi"));
@endphp
@if(auth()->check() && auth()->user()->isOwner() && request()->routeIs('panel.*'))
@php
  $ownerQuickLinkOptions = [
    'panel.reservations' => ['label' => 'Rezervasyonlar', 'url' => route('panel.reservations')],
    'panel.hotel-chats' => ['label' => 'Müşteri sohbetleri', 'url' => route('panel.hotel-chats')],
    'panel.guests' => ['label' => 'Misafir CRM', 'url' => route('panel.guests')],
    'panel.reports' => ['label' => 'Raporlar', 'url' => route('panel.reports')],
    'panel.finance' => ['label' => 'Cari ve muhasebe', 'url' => route('panel.finance')],
    'panel.invoices' => ['label' => 'Faturalar', 'url' => route('panel.invoices')],
    'panel.subscriptions' => ['label' => 'Abonelikler', 'url' => route('panel.subscriptions')],
    'panel.security' => ['label' => 'Güvenlik geçmişi', 'url' => route('panel.security')],
    'panel.help' => ['label' => 'Yardım merkezi', 'url' => route('panel.help')],
  ];
  $ownerQuickLinks = collect((array) auth()->user()->owner_quick_links)
      ->filter(fn ($key) => isset($ownerQuickLinkOptions[$key]))
      ->unique()
      ->take(5)
      ->mapWithKeys(fn ($key) => [$key => $ownerQuickLinkOptions[$key]]);
@endphp
<header class="owner-app-header"><div class="owner-app-header-inner">
  <a class="owner-app-brand" href="{{ route('panel.index') }}" aria-label="İş Ortağı Paneli ana sayfa"><span class="owner-app-mark" aria-hidden="true"><svg viewBox="0 0 40 40" focusable="false"><path d="M20 21.2c-4.9 0-10.8 4.4-10.8 9.1 0 3.1 2.5 4.8 5.8 4.1 3.4-.8 6.6-.8 10 0 3.3.7 5.8-1 5.8-4.1 0-4.7-5.9-9.1-10.8-9.1Z"/><ellipse cx="8.5" cy="16.2" rx="3.4" ry="4.3"/><ellipse cx="16.4" cy="10.2" rx="3.6" ry="4.5"/><ellipse cx="25.2" cy="10.2" rx="3.6" ry="4.5"/><ellipse cx="32" cy="16.5" rx="3.4" ry="4.3"/></svg></span><span>Partner Pet Otel<small>İş Ortağı Paneli · {{ auth()->user()->name }}</small></span></a>
  <div class="owner-app-header-actions"><span class="owner-app-context">Önbüro ve işletme yönetimi</span>@include('components.panel-search')<button class="owner-sidebar-toggle" type="button" aria-controls="owner-panel-sidebar" aria-expanded="false"><span aria-hidden="true">☰</span> Menü</button><form method="post" action="{{ route('logout') }}">@csrf<button class="owner-app-logout" type="submit"><span aria-hidden="true">↪</span> Çıkış yap</button></form></div>
</div>
<nav class="owner-app-shortcuts" aria-label="Sık kullanılan menüler">
  <strong>Kısayollar</strong>
  @forelse($ownerQuickLinks as $routeName => $item)
    <a href="{{ $item['url'] }}" @if(request()->routeIs($routeName.'*')) aria-current="page" @endif>{{ $item['label'] }}</a>
  @empty
    <span class="owner-shortcuts-empty">Sık kullandığınız sayfaları buraya ekleyin.</span>
  @endforelse
  <details class="owner-shortcuts-editor"><summary>＋ Düzenle</summary>
    <form method="post" action="{{ route('panel.quick-links.update') }}">@csrf
      <b>Hızlı erişimde gösterilecek menüler</b>
      <small>En fazla 5 menü seçebilirsiniz.</small>
      @foreach($ownerQuickLinkOptions as $routeName => $item)
        <label><input type="checkbox" name="quick_links[]" value="{{ $routeName }}" @checked($ownerQuickLinks->has($routeName))><span>{{ $item['label'] }}</span></label>
      @endforeach
      <button type="submit">Kısayolları kaydet</button>
    </form>
  </details>
</nav></header>
@else
<header><div class="wrap"><nav>
<a class="logo" href="{{ route('home') }}" aria-label="{{ $siteBrand }} ana sayfa">
  @if($siteLogo !== '')<img class="brand-logo-image" src="{{ asset('storage/'.$siteLogo) }}" alt="{{ $siteLogoText ?: $siteBrand }}">@else<span class="brand-paw" aria-hidden="true"><svg viewBox="0 0 44 44" focusable="false">
    <defs><linearGradient id="brand-paw-gradient" x1="0" y1="0" x2="1" y2="1"><stop stop-color="#104d37"/><stop offset="1" stop-color="#176447"/></linearGradient></defs>
    <rect width="44" height="44" rx="14" fill="url(#brand-paw-gradient)" stroke="#fffefa" stroke-opacity=".2"/>
    <circle cx="32" cy="11" r="5" fill="#d2b167"/>
    <path d="M6 30c4-2.6 8-2.6 12 0s8 2.6 12 0 6-2 8-1" fill="none" stroke="#b8d5c0" stroke-linecap="round" stroke-width="2.2"/>
    <ellipse cx="22" cy="29" rx="8" ry="6.4" fill="#fffefa"/>
    <ellipse cx="13.5" cy="21.8" rx="2.6" ry="3.4" transform="rotate(-22 13.5 21.8)" fill="#fffefa"/>
    <ellipse cx="19.4" cy="18.5" rx="2.5" ry="3.4" transform="rotate(-8 19.4 18.5)" fill="#fffefa"/>
    <ellipse cx="25.9" cy="18.7" rx="2.5" ry="3.4" transform="rotate(8 25.9 18.7)" fill="#fffefa"/>
    <ellipse cx="31.5" cy="22.2" rx="2.6" ry="3.4" transform="rotate(22 31.5 22.2)" fill="#fffefa"/>
  </svg></span>@endif
  @if($siteLogoText !== '')<span class="brand-wordmark">{{ $siteLogoText }}<small>Antalya’nın pet otel rehberi</small></span>@elseif($siteLogo === '')<span class="brand-wordmark">{{ $siteBrand }}<small>Antalya’nın pet otel rehberi</small></span>@endif
</a>
<div class="dnav">
  @foreach($headerLinks as $link)<a href="{{ $link['url'] }}" @if($link['label']==='Otelini Kaydet') class="dnav-cta" aria-label="Pet oteliniz mi var? Hemen aramıza katılın" @endif>{{ $link['label']==='Otelini Kaydet' ? 'Pet oteliniz mi var? Aramıza katılın' : $link['label'] }}</a>@endforeach
  @auth
    @php
      $accountUrl = auth()->user()->isAdmin() ? route('admin.index') : (auth()->user()->isOwner() ? route('panel.index') : route('pets.index'));
      $chatUrl = auth()->user()->isAdmin() ? route('admin.hotel-chats') : (auth()->user()->isOwner() ? route('panel.hotel-chats') : route('customer.hotel-chats'));
      $securityUrl = auth()->user()->isOwner() ? route('panel.security') : route('security.index');
    @endphp
    <details class="dnav-account-menu">
      <summary>Hesabım <span aria-hidden="true">⌄</span></summary>
      <div class="dnav-account-popover">
        <a href="{{ $accountUrl }}">Profilim</a>
        @if(!auth()->user()->isOwner())<a href="{{ route('reservations.index') }}">Rezervasyonlarım</a>@endif
        <a href="{{ $chatUrl }}">Sohbetler</a>
        @unless(auth()->user()->isAdmin())<a href="{{ $securityUrl }}">Hesap güvenliği</a>@endunless
        @unless(auth()->user()->isAdmin())<a href="{{ route('invoices.index') }}">Faturalar</a>@endunless
      </div>
    </details>
    <form class="dnav-logout" method="post" action="{{ route('logout') }}">@csrf<button type="submit">Çıkış</button></form>
  @else
    <a class="dnav-account" href="{{ route('login') }}">Giriş yap</a>
    <a class="dnav-register" href="{{ route('register') }}">Kayıt ol</a>
  @endauth
</div>
<form class="hsearch" action="{{ route('search') }}" method="get" role="search">
  <label for="hs" class="skip">Otel ara</label>
  <span aria-hidden="true">&#128269;</span>
  <input id="hs" type="search" name="q" placeholder="Otel veya ilçe ara" value="{{ request('q') }}">
  <button type="submit">Ara</button>
</form>
<details class="mnav">
  <summary aria-label="Menüyü aç"><i aria-hidden="true"></i></summary>
  <div class="mpanel">
    <form class="menu-search" action="{{ route('search') }}" method="get" role="search">
      <label class="skip" for="menu-search-q">Otel veya ilçe ara</label>
      <input id="menu-search-q" type="search" name="q" value="{{ request('q') }}" placeholder="Otel veya ilçe ara">
      <button class="btn" type="submit">Ara</button>
    </form>
    <div class="grp">Hesap</div>
    @auth
      @if(auth()->user()->role === 'admin')<a href="{{ route('admin.index') }}">Yönetim</a>@endif
      @if(auth()->user()->isOwner())<a href="{{ route('panel.index') }}">Otel Paneli</a>@endif
      @if(auth()->user()->isAdmin())<a href="{{ route('admin.hotel-chats') }}">Sohbetler</a>@elseif(auth()->user()->isOwner())<a href="{{ route('panel.hotel-chats') }}">Sohbetler</a>@else<a href="{{ route('customer.hotel-chats') }}">Sohbetler</a>@endif
      <a href="{{ route('reservations.index') }}">Rezervasyonlarım</a>
      @unless(auth()->user()->isAdmin())<a href="{{ route('invoices.index') }}">Faturalarım</a>@endunless
      <a href="{{ route('shop.orders') }}">Siparişlerim</a>
      <a href="{{ route('pets.index') }}">Dostlarım</a>
      @unless(auth()->user()->isAdmin())<a href="{{ auth()->user()->isOwner() ? route('panel.security') : route('security.index') }}">Hesap güvenliği</a>@endunless
      <form method="post" action="{{ route('logout') }}">@csrf<button class="link" type="submit">Çıkış yap</button></form>
    @else
      <div class="mnav-auth-shortcuts" aria-label="Hesap kısayolları">
        <a href="{{ route('login') }}">Giriş yap</a>
        <a href="{{ route('register') }}">Kayıt ol</a>
      </div>
    @endauth
    @guest<a class="mnav-partner-cta" href="{{ route('partner.create') }}"><strong>Pet oteliniz mi var?</strong><span>Hemen aramıza katılın <b aria-hidden="true">→</b></span></a>@endguest
    <div class="grp">Keşfet</div>
    <details class="menu-category">
      <summary><span aria-hidden="true">🏨</span> Pet otelleri <span class="menu-chevron" aria-hidden="true">⌄</span></summary>
      <div class="menu-subitems">
        <a href="{{ url('/kedi-kopek-otelleri') }}">{{ $siteCity ?: 'Pet' }}’daki tüm pet otelleri <span>→</span></a>
        <a href="{{ url('/kopek-otelleri') }}">Köpek otelleri <span>→</span></a>
        <a href="{{ url('/kedi-otelleri') }}">Kedi otelleri <span>→</span></a>
      </div>
    </details>
    <details class="menu-category">
      <summary><span aria-hidden="true">📍</span> İlçeye göre <span class="menu-chevron" aria-hidden="true">⌄</span></summary>
      <div class="menu-subitems">
        <a href="{{ url('/aksu') }}">Aksu <span>→</span></a>
        <a href="{{ url('/alanya') }}">Alanya <span>→</span></a>
        <a href="{{ url('/konyaalti') }}">Konyaaltı <span>→</span></a>
        <a href="{{ url('/muratpasa') }}">Muratpaşa <span>→</span></a>
        <a href="{{ url('/serik') }}">Serik <span>→</span></a>
      </div>
    </details>
    <details class="menu-category">
      <summary><span aria-hidden="true">🛏️</span> Oda tipine göre <span class="menu-chevron" aria-hidden="true">⌄</span></summary>
      <div class="menu-subitems">
        <a href="{{ url('/oda-tipi/buyuk-irk-kopek-odasi') }}">Büyük ırk köpek odası <span>→</span></a>
        <a href="{{ url('/oda-tipi/orta-irk-kopek-odasi') }}">Orta ırk köpek odası <span>→</span></a>
        <a href="{{ url('/oda-tipi/kucuk-irk-kopek-odasi') }}">Küçük ırk köpek odası <span>→</span></a>
        <a href="{{ url('/oda-tipi/kedi-odasi') }}">Kedi odası <span>→</span></a>
      </div>
    </details>
    <div class="grp">Diğer</div>
    @foreach($headerLinks as $link)
      @unless(in_array(mb_strtolower($link['label'], 'UTF-8'), ['pet otel', 'kedi oteli', 'köpek oteli'], true))
        <a href="{{ $link['url'] }}">{{ $link['label'] }} <span aria-hidden="true">&#8594;</span></a>
      @endunless
    @endforeach

    <div class="grp">Kurumsal</div>
    <a href="{{ route('pages.show', 'hakkimizda') }}">Hakkımızda</a>
    <a href="{{ route('pages.show', 'nasil-calisir') }}">Nasıl Çalışır?</a>
    <a href="{{ route('pages.show', 'sss') }}">Sıkça Sorulan Sorular</a>
    <a href="{{ route('hub.kategori') }}">Kategoriler</a>
    <a href="{{ route('hub.yardim') }}">Yardım Merkezi</a>
    <a href="{{ route('hub.harita') }}">Site Haritası</a>
    <a href="{{ route('pages.show', 'pet-otel-nedir') }}">Pet Otel Nedir?</a>
    <a href="{{ route('contact') }}">İletişim</a>

    <div class="grp">İşletmeler</div>
    <a href="{{ route('partner.create') }}">Otelinizi Yayınlatın</a>

  </div>
</details>
</nav></div></header>
@endif
@if(!request()->routeIs('panel.*') && !request()->routeIs('admin.*'))
<nav class="mobile-bottom-nav" aria-label="Mobil hızlı erişim">
  <a href="{{ route('home') }}" @if(request()->routeIs('home')) aria-current="page" @endif>
    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m3 10 9-7 9 7v10a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z"/></svg>
    <span>Ana sayfa</span>
  </a>
  <a href="{{ route('hotels.all') }}" @if(request()->routeIs('hotels.*')) aria-current="page" @endif>
    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 21V5l8-3 8 3v16M2 21h20M9 8h1m4 0h1m-6 4h1m4 0h1m-5 9v-4h4v4"/></svg>
    <span>Oteller</span>
  </a>
  <a class="mobile-bottom-nav__search" href="{{ route('home') }}#home-search">
    <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="10.8" cy="10.8" r="6.8"/><path d="m16 16 5 5"/></svg>
    <span>Ara</span>
  </a>
  @auth
    @php
      $mobileAccountUrl = auth()->user()->isAdmin() ? route('admin.index') : (auth()->user()->isOwner() ? route('panel.index') : route('pets.index'));
      $mobileMessagesUrl = auth()->user()->isAdmin() ? route('admin.hotel-chats') : (auth()->user()->isOwner() ? route('panel.hotel-chats') : route('reservations.index'));
    @endphp
    <a href="{{ $mobileMessagesUrl }}"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M21 11.5a8.5 8.5 0 0 1-12.5 7.5L3 21l1.8-5.5A8.5 8.5 0 1 1 21 11.5Z"/><path d="M8 11h8m-8 3h5"/></svg><span>{{ auth()->user()->isOwner() || auth()->user()->isAdmin() ? 'Mesajlar' : 'Taleplerim' }}</span></a>
    <a href="{{ $mobileAccountUrl }}"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></svg><span>Hesabım</span></a>
  @else
    <a href="{{ route('login') }}" @if(request()->routeIs('login')) aria-current="page" @endif><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M10 17l5-5-5-5m5 5H3m9-9h7a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-7"/></svg><span>Giriş</span></a>
    <a href="{{ route('register') }}" @if(request()->routeIs('register')) aria-current="page" @endif><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="9" cy="8" r="4"/><path d="M2 21v-2a7 7 0 0 1 14 0v2m4-13v8m-4-4h8"/></svg><span>Kayıt ol</span></a>
  @endauth
</nav>
@endif
@if(auth()->check() && auth()->user()->isOwner() && request()->routeIs('panel.*'))
<nav class="owner-panel-nav" id="owner-panel-sidebar" aria-label="İşletme paneli">
<div class="owner-sidebar-heading"><div><small>ÖNBÜRO</small><b>İşletme menüsü</b></div><button class="owner-sidebar-close" type="button" aria-label="Menüyü kapat">×</button></div>
<div class="wrap owner-nav-groups">
<div class="owner-nav-group"><span>GENEL</span><a class="owner-nav-link" href="{{ route('panel.index') }}" @if(request()->routeIs('panel.index')) aria-current="page" @endif><i class="owner-nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="m3 10 9-7 9 7v10a1 1 0 0 1-1 1h-5v-7H9v7H4a1 1 0 0 1-1-1Z"/></svg></i><span>Genel bakış</span></a></div>
<div class="owner-nav-group"><span>REZERVASYON VE MİSAFİR</span><a class="owner-nav-link" href="{{ route('panel.reservations') }}" @if(request()->routeIs('panel.reservations*')) aria-current="page" @endif><i class="owner-nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18M8 14h3m-3 3h6"/></svg></i><span>Rezervasyonlar</span></a><a class="owner-nav-link" href="{{ route('panel.guests') }}" @if(request()->routeIs('panel.guests')) aria-current="page" @endif><i class="owner-nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v2m6-10a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm8-1a4 4 0 1 0 0-8m4 19v-2a4 4 0 0 0-3-3.87"/></svg></i><span>Misafir CRM</span></a><a class="owner-nav-link" href="{{ route('panel.hotel-chats') }}" @if(request()->routeIs('panel.hotel-chats')) aria-current="page" @endif><i class="owner-nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M21 11.5a8.5 8.5 0 0 1-12.5 7.5L3 21l1.8-5.5A8.5 8.5 0 1 1 21 11.5Z"/><path d="M8 11h8m-8 3h5"/></svg></i><span>Müşteri sohbetleri</span></a></div>
<div class="owner-nav-group"><span>İŞLETME VE RAPORLAR</span><a class="owner-nav-link" href="{{ route('panel.statistics') }}" @if(request()->routeIs('panel.statistics')) aria-current="page" @endif><i class="owner-nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M4 19V5m0 14h17M8 15l3-4 3 2 5-7"/><path d="M8 8h.01M14 12h.01M19 6h.01"/></svg></i><span>İstatistikler</span></a><a class="owner-nav-link" href="{{ route('panel.reports') }}" @if(request()->routeIs('panel.reports')) aria-current="page" @endif><i class="owner-nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M4 19V5m0 14h17M8 15l3-4 3 2 5-7"/></svg></i><span>Raporlar</span></a><a class="owner-nav-link" href="{{ route('panel.subscriptions') }}" @if(request()->routeIs('panel.subscriptions*')) aria-current="page" @endif><i class="owner-nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 10h18m-14 5h4"/></svg></i><span>Abonelik ve paketler</span></a></div>
<div class="owner-nav-group"><span>MUHASEBE</span><a class="owner-nav-link" href="{{ route('panel.finance') }}" @if(request()->routeIs('panel.finance*')) aria-current="page" @endif><i class="owner-nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 10h18m-14 5h4"/></svg></i><span>Cari hesap</span></a><a class="owner-nav-link" href="{{ route('panel.invoices') }}" @if(request()->routeIs('panel.invoices')) aria-current="page" @endif><i class="owner-nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M6 3h9l4 4v14H6a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Z"/><path d="M14 3v5h5M8 13h8m-8 4h8"/></svg></i><span>Faturalar</span></a></div>
<div class="owner-nav-group"><span>TESİSLERİM</span><a class="owner-nav-link" href="{{ route('panel.hotel.create') }}" @if(request()->routeIs('panel.hotel.create*')) aria-current="page" @endif><i class="owner-nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg></i><span>Yeni tesis ekle</span></a><details class="owner-nav-selector" @if(request()->routeIs('panel.hotel.*') || request()->routeIs('panel.shop.*')) open @endif><summary><span><i class="owner-nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M3 21h18M5 21V7l7-4 7 4v14M9 10h1m4 0h1m-6 4h1m4 0h1m-6 7v-4h4v4"/></svg></i> Tesislerim</span><b>{{ auth()->user()->hotels->count() }}</b></summary><div class="owner-nav-hotel-popover">
@forelse(auth()->user()->hotels as $navHotel)
@php
    $isCurrentNavHotel = (int) (request()->route('hotel')?->id ?? 0) === (int) $navHotel->id;
@endphp
<section class="owner-nav-hotel"><details @if($isCurrentNavHotel && (request()->routeIs('panel.hotel.*') || request()->routeIs('panel.shop.*'))) open @endif><summary>{{ $navHotel->name }}<span aria-hidden="true">⌄</span></summary><div class="owner-nav-hotel-links"><a href="{{ route('panel.hotel.edit',$navHotel) }}" @if($isCurrentNavHotel && request()->routeIs('panel.hotel.edit')) aria-current="page" @endif><i aria-hidden="true">⌂</i>Tesis profili</a><a href="{{ route('panel.hotel.section',[$navHotel,'widget']) }}" @if($isCurrentNavHotel && request()->route('section')==='widget') aria-current="page" @endif><i aria-hidden="true">▣</i>Rezervasyon kutusu</a><a href="{{ route('panel.hotel.section',[$navHotel,'rooms']) }}" @if($isCurrentNavHotel && request()->route('section')==='rooms') aria-current="page" @endif><i aria-hidden="true">▦</i>Odalar ve doluluk</a><a href="{{ route('panel.hotel.section',[$navHotel,'extras']) }}" @if($isCurrentNavHotel && request()->route('section')==='extras') aria-current="page" @endif><i aria-hidden="true">✦</i>Ek hizmetler</a><a href="{{ route('panel.hotel.section',[$navHotel,'campaigns']) }}" @if($isCurrentNavHotel && request()->route('section')==='campaigns') aria-current="page" @endif><i aria-hidden="true">％</i>Kampanyalar</a><a class="owner-nav-shop-link" href="{{ route('panel.shop.products',$navHotel) }}" @if($isCurrentNavHotel && request()->routeIs('panel.shop.*')) aria-current="page" @endif><i aria-hidden="true">▣</i>Mağaza ürünleri <span aria-hidden="true">→</span></a></div></details></section>
@empty
<p>Bu hesapta henüz tesis yok.</p>
@endforelse
</div></details></div>
<div class="owner-nav-group"><span>GÜVENLİK</span><a class="owner-nav-link" href="{{ route('panel.security') }}" @if(request()->routeIs('panel.security')) aria-current="page" @endif><i class="owner-nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M12 22s8-4 8-11V5l-8-3-8 3v6c0 7 8 11 8 11Z"/><path d="m9 12 2 2 4-4"/></svg></i><span>Giriş ve işlem geçmişi</span></a></div>
</div><div class="owner-sidebar-help"><span>?</span><div><b>İşletme kullanım kılavuzu</b><a href="{{ route('panel.help') }}">İşletme destek merkezini aç <span aria-hidden="true">→</span></a></div></div></nav><button class="owner-sidebar-backdrop" type="button" aria-label="Menüyü kapat" tabindex="-1"></button>
@endif
@if(auth()->check() && auth()->user()->isAdmin() && request()->routeIs('admin.*'))
<nav class="owner-panel-nav" aria-label="Yönetim paneli"><div class="wrap">
<a href="{{ route('admin.index') }}">Yönetim ana sayfa</a>
<a href="{{ route('admin.adoption.queue') }}" @if(request()->routeIs('admin.adoption.*')) aria-current="page" @endif>Sahiplendirme onayı</a>
<a href="{{ route('admin.forum.queue') }}" @if(request()->routeIs('admin.forum.*')) aria-current="page" @endif>Forum moderasyonu</a>
<a href="{{ route('admin.security') }}" @if(request()->routeIs('admin.security')) aria-current="page" @endif>Güvenlik kayıtları</a>
<a href="{{ route('admin.subscriptions.index') }}" @if(request()->routeIs('admin.subscriptions.*')) aria-current="page" @endif>Abonelik ve planlar</a>
</div></nav>
@endif
<main id="icerik" class="{{ request()->routeIs('panel.*') ? 'owner-panel' : '' }}">
@if(session('ok'))<div class="wrap"><div class="flash ok">{{ session('ok') }}</div></div>@endif
@if(session('warning'))<div class="wrap"><div class="flash err">{{ session('warning') }}</div></div>@endif
@if($errors->any())<div class="wrap"><div class="flash err">@foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div></div>@endif
@yield('content')
</main>
<footer><div class="wrap footer-layout">
<div class="footer-main">
<div class="fcol footer-brand">
  <b class="footer-brand-name">{{ \App\Models\Setting::al('site_name', $siteBrand) }}</b>
  <p class="hint">{{ \App\Models\Setting::al('footer_description', ($siteCity ?: $siteBrand).' bölgesindeki kedi ve köpek konaklama tesislerini; oda tipi, günlük fiyat, giriş-çıkış saatleri ve kurallara göre karşılaştırın.') }}</p>
  <p class="hint">{{ \App\Models\Setting::al('booking_requests_enabled', '0') === '1' ? 'Talepler platforma kaydedilir; e-posta iletimi doğrulanamazsa uyarı gösterilir. Rezervasyon işletme onayıyla kesinleşir.' : 'Sitemiz şu anda test aşamasındadır. Çevrimiçi rezervasyon talepleri işletmelere iletilmemektedir.' }}</p>
  @php($footerPhone = trim((string) \App\Models\Setting::al('site_phone', '')))
  <div class="footer-contact">
    <span class="footer-contact-label">İLETİŞİM</span>
    @if(\App\Models\Setting::al('site_address', '') !== '')<span aria-label="Adres">⌖ {{ \App\Models\Setting::al('site_address', '') }}</span>@endif
    @if($footerPhone !== '')<a href="tel:{{ preg_replace('/[^0-9+]/', '', $footerPhone) }}">☎ {{ $footerPhone }}</a>@endif
  </div>
</div>
<nav class="footer-navigation" aria-label="Alt menü">
  <div class="fcol">
    <b>Keşfet</b>
    @foreach($footerExplore as $link)<a href="{{ $link['url'] }}">{{ $link['label'] }}</a>@endforeach
  </div>
  <div class="fcol">
    <b>Kurumsal</b>
    @foreach($footerCorporate as $link)<a href="{{ $link['url'] }}">{{ $link['label'] }}</a>@endforeach
  </div>
  <div class="fcol">
    <b>Pet Oteller</b>
    <a href="https://www.google.com/maps/place/Antalya+Pet+Otel/@36.9046874,30.6102606,12z/data=!4m7!3m6!1s0x14c3855bf230e269:0xe1df11f15aa66155!8m2!3d36.8589909!4d30.8165947!15sChBhbnRhbHlhIHBldCBvdGVsWhIiEGFudGFseWEgcGV0IG90ZWySARRwZXRfYm9hcmRpbmdfc2VydmljZZoBI0NoWkRTVUhOTUc5blMwVkpRMEZuU1VSS0xVOHpTMVJuRUFF4AEA-gEECFcQTA!16s%2Fg%2F11fmc6sfzf?entry=tts&g_ep=EgoyMDI2MTAxMy4wIPu8ASoASAFQAw%3D%3D&skid=91d2d174-fe2e-4bb7-9657-0d43269ae7a5" target="_blank" rel="noopener">Antalya Pet Otel <span aria-hidden="true">↗</span></a>
    <a href="https://bodrumpetotel.com">Bodrum Pet Otel</a>
    <a href="https://ankarapetotel.com">Ankara Pet Otel</a>
    <a href="https://istanbulpetotelleri.com">İstanbul Pet Otel</a>
    <a href="https://xpetotel.com">Pet Otel</a>
  </div>
  <div class="fcol">
    <b>Yasal</b>
    @foreach($footerLegal as $link)<a href="{{ $link['url'] }}">{{ $link['label'] }}</a>@endforeach
    @if($analyticsId && $analyticsPageAllowed)<a href="#analytics-consent" data-cookie-preferences>Çerez tercihlerini yönet</a>@endif
  </div>
</nav>
</div>
<div class="fbot">{{ str_replace(['{year}', 'Antalya Pet Otel'], [date('Y'), $siteBrand], \App\Models\Setting::al('footer_copyright', '© {year} ' . $siteBrand)) }}</div>
</div></footer>
@auth
@if(!auth()->user()->isAdmin())
@include('components.activity-sound')
<script>
(()=> {
  const box=document.querySelector('.apo-activity');
  if(!box || !('Notification' in window) || !('serviceWorker' in navigator) || !('PushManager' in window))return;
  const button=document.createElement('button');
  button.type='button';button.className='apo-activity-toggle apo-push-toggle';
  button.style.cssText='background:#edf3ff;color:#34588a';
  box.appendChild(button);
  const status=box.querySelector('[aria-live]');
  const csrf=@json(csrf_token());
  const urls={key:@json(route('push.public-key')),subscribe:@json(route('push.subscriptions.store')),remove:@json(route('push.subscriptions.destroy')),worker:@json(route('push.worker')),fcmWorker:@json(route('push.fcm-worker')),fcmConfig:@json(route('push.fcm-config')),fcmSave:@json(route('push.fcm-subscriptions.store')),fcmRemove:@json(route('push.fcm-subscriptions.destroy'))};
  const isIos=/iphone|ipad|ipod/i.test(navigator.userAgent);
  const standalone=window.matchMedia('(display-mode: standalone)').matches||navigator.standalone===true;
  let current=null,currentFcmToken=null,messagingApi=null;
  const update=()=>{const enabled=!!current||!!currentFcmToken;button.textContent=enabled?'📲 Bildirimleri kapat':'📲 Telefon bildirimini aç';button.setAttribute('aria-pressed',String(enabled));};
  const keyBytes=value=>{const pad='='.repeat((4-value.length%4)%4),raw=atob((value+pad).replace(/-/g,'+').replace(/_/g,'/'));return Uint8Array.from(raw,c=>c.charCodeAt(0));};
  async function save(sub){
    const json=sub.toJSON();
    const response=await fetch(urls.subscribe,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':csrf,'X-Requested-With':'XMLHttpRequest'},body:JSON.stringify({...json,contentEncoding:(PushManager.supportedContentEncodings||['aes128gcm'])[0]})});
    if(!response.ok)throw new Error('Bildirim kaydı tamamlanamadı. Yeniden deneyin.');
    current=sub;update();status.textContent='Telefon ve tarayıcı bildirimleri açıldı.';
  }
  async function fcmApi(){
    if(messagingApi)return messagingApi;
    const cfgResponse=await fetch(urls.fcmConfig,{credentials:'same-origin',headers:{Accept:'application/json'},cache:'no-store'});
    if(!cfgResponse.ok)throw new Error('Firebase bildirim ayarı alınamadı.');
    const cfg=await cfgResponse.json();
    const appSdk=await import('https://www.gstatic.com/firebasejs/12.19.0/firebase-app.js');
    const messagingSdk=await import('https://www.gstatic.com/firebasejs/12.19.0/firebase-messaging.js');
    if(!await messagingSdk.isSupported())throw new Error('Bu tarayıcı Firebase bildirimlerini desteklemiyor.');
    const app=appSdk.initializeApp(cfg.firebase,'apo-fcm');
    messagingApi={sdk:messagingSdk,messaging:messagingSdk.getMessaging(app),vapidKey:cfg.vapid_key};
    return messagingApi;
  }
  async function saveFcmToken(token){
    const response=await fetch(urls.fcmSave,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':csrf,'X-Requested-With':'XMLHttpRequest'},body:JSON.stringify({token})});
    if(!response.ok)throw new Error('Firebase bildirim kaydı tamamlanamadı.');
    currentFcmToken=token;update();
  }
  async function activeRegistration(registration){
    if(registration.active?.state==='activated')return registration;
    const worker=registration.installing||registration.waiting;
    if(!worker)throw new Error('Firebase bildirim servisi başlatılamadı.');
    if(worker.state==='activated')return registration;
    await new Promise((resolve,reject)=>worker.addEventListener('statechange',()=>{if(worker.state==='activated')resolve();else if(worker.state==='redundant')reject(new Error('Firebase bildirim servisi başlatılamadı.'));}));
    return registration;
  }
  async function enableFcm(){
    const api=await fcmApi();
    const registration=await navigator.serviceWorker.register(urls.fcmWorker,{scope:'/fcm/'});
    await activeRegistration(registration);
    const token=await api.sdk.getToken(api.messaging,{vapidKey:api.vapidKey,serviceWorkerRegistration:registration});
    if(!token)throw new Error('Firebase bildirim anahtarı alınamadı.');
    await saveFcmToken(token);
  }
  async function removeFcm(){
    if(!currentFcmToken)return;
    await fetch(urls.fcmRemove,{method:'DELETE',credentials:'same-origin',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':csrf,'X-Requested-With':'XMLHttpRequest'},body:JSON.stringify({token:currentFcmToken})});
    if(messagingApi)try{await messagingApi.sdk.deleteToken(messagingApi.messaging);}catch(_){}
    currentFcmToken=null;update();
  }
  async function restore(){
    try{
      const registration=await navigator.serviceWorker.register(urls.worker,{scope:'/'});
      const ready=await navigator.serviceWorker.ready;
      if(!ready.active)throw new Error('Bildirim servisi henüz hazır değil. Sayfayı yenileyip tekrar deneyin.');
      current=await ready.pushManager.getSubscription();
      if(Notification.permission==='granted'){
        if(current)await save(current);
        try{await enableFcm();}catch(_){}
      }
      update();
    }catch(_){update();}
  }
  button.addEventListener('click',async()=>{
    button.disabled=true;
    try{
      if(current||currentFcmToken){
        const subscription=current||await (await navigator.serviceWorker.ready).pushManager.getSubscription();
        if(subscription){const endpoint=subscription.endpoint;await subscription.unsubscribe();await fetch(urls.remove,{method:'DELETE',credentials:'same-origin',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':csrf,'X-Requested-With':'XMLHttpRequest'},body:JSON.stringify({endpoint})});}
        await removeFcm();current=null;update();status.textContent='Telefon bildirimleri kapatıldı.';return;
      }
      if(isIos&&!standalone){status.textContent='iPhone/iPad’de bildirim için önce Paylaş menüsünden “Ana Ekrana Ekle”yi seçip uygulamayı açın.';return;}
      const permission=Notification.permission==='granted'?'granted':await Notification.requestPermission();
      if(permission!=='granted'){status.textContent='Bildirim izni verilmedi. İsterseniz tarayıcı ayarlarından açabilirsiniz.';return;}
      const registration=await navigator.serviceWorker.register(urls.worker,{scope:'/'});
      const ready=await navigator.serviceWorker.ready;
      if(!ready.active)throw new Error('Bildirim servisi henüz hazır değil. Sayfayı yenileyip tekrar deneyin.');
      let fcmEnabled=false;
      try{await enableFcm();fcmEnabled=true;}catch(_){}
      const keyResponse=await fetch(urls.key,{credentials:'same-origin',headers:{Accept:'application/json'},cache:'no-store'});
      if(!keyResponse.ok)throw new Error('Bildirim ayarı alınamadı.');
      const {public_key}=await keyResponse.json();
      let sub=await ready.pushManager.getSubscription();
      if(!sub)sub=await ready.pushManager.subscribe({userVisibleOnly:true,applicationServerKey:keyBytes(public_key)});
      await save(sub);
      if(fcmEnabled)status.textContent='Firebase ile telefon ve tarayıcı bildirimleri açıldı.';
    }catch(error){status.textContent=error.message||'Bildirim açılamadı. Tarayıcı ayarlarınızı kontrol edin.';}
    finally{button.disabled=false;}
  });
  if(Notification.permission==='granted')restore();else update();
})();
</script>
@endif
@endauth
@if(auth()->check() && auth()->user()->isOwner() && request()->routeIs('panel.*'))
<script>
(()=>{const side=document.getElementById('owner-panel-sidebar'),toggle=document.querySelector('.owner-sidebar-toggle'),close=document.querySelector('.owner-sidebar-close'),backdrop=document.querySelector('.owner-sidebar-backdrop');if(!side||!toggle)return;const setOpen=(open)=>{document.body.classList.toggle('owner-sidebar-open',open);toggle.setAttribute('aria-expanded',String(open));};toggle.addEventListener('click',()=>setOpen(!document.body.classList.contains('owner-sidebar-open')));[close,backdrop].forEach(el=>el&&el.addEventListener('click',()=>setOpen(false)));document.addEventListener('keydown',e=>{if(e.key==='Escape')setOpen(false)});side.querySelectorAll('a').forEach(a=>a.addEventListener('click',()=>{if(matchMedia('(max-width:1080px)').matches)setOpen(false)}));const form=document.querySelector('.owner-shortcuts-editor form');if(form){const boxes=[...form.querySelectorAll('input[type="checkbox"]')];const sync=()=>{const count=boxes.filter(box=>box.checked).length;boxes.forEach(box=>box.disabled=!box.checked&&count>=5)};boxes.forEach(box=>box.addEventListener('change',sync));sync()}})();
</script>
@endif
@if(auth()->check() && auth()->user()->isOwner() && request()->routeIs('panel.reservations'))
<style>
@media(max-width:720px){
  .owner-panel .page nav.stay-filter{display:flex!important;flex-direction:column!important;position:relative!important;inset:auto!important;gap:8px!important;margin:18px 0 20px!important}
  .owner-panel .page>.chips{position:relative!important;inset:auto!important;display:flex!important;flex-wrap:wrap!important;gap:7px!important;margin:0 0 17px!important;clear:both}

  .owner-panel .page>table.table{display:block!important;width:100%!important;min-width:0!important;border:0!important;border-radius:0!important;background:transparent!important;overflow:visible!important;box-shadow:none!important}
  .owner-panel .page>table.table>tbody{display:grid;gap:12px}
  .owner-panel .page>table.table>tbody>tr:first-child{display:none}
  .owner-panel .page>table.table>tbody>tr:not(:first-child){display:grid;grid-template-columns:repeat(2,minmax(0,1fr));overflow:hidden;border:1px solid #e6e8e1;border-radius:16px;background:#fff;box-shadow:0 5px 16px rgba(36,48,39,.05)}
  .owner-panel .page>table.table>tbody>tr:not(:first-child)>td{display:grid;align-content:start;gap:5px;min-width:0;padding:12px!important;border-bottom:1px solid #edf0eb!important;font-size:13px!important;line-height:1.55;overflow-wrap:anywhere}
  .owner-panel .page>table.table>tbody>tr:not(:first-child)>td:before{display:block;color:#7a857c;font-size:9px;font-weight:900;letter-spacing:.08em;text-transform:uppercase}
  .owner-panel .page>table.table>tbody>tr:not(:first-child)>td:nth-child(1):before{content:'Rezervasyon'}
  .owner-panel .page>table.table>tbody>tr:not(:first-child)>td:nth-child(2):before{content:'Misafir ve pet'}
  .owner-panel .page>table.table>tbody>tr:not(:first-child)>td:nth-child(3):before{content:'Oda ve tarih'}
  .owner-panel .page>table.table>tbody>tr:not(:first-child)>td:nth-child(4):before{content:'Tutar ve ödeme'}
  .owner-panel .page>table.table>tbody>tr:not(:first-child)>td:nth-child(5):before{content:'Durum'}
  .owner-panel .page>table.table>tbody>tr:not(:first-child)>td:nth-child(6):before{content:'İşlemler'}
  .owner-panel .page>table.table>tbody>tr:not(:first-child)>td:nth-child(1),.owner-panel .page>table.table>tbody>tr:not(:first-child)>td:nth-child(2),.owner-panel .page>table.table>tbody>tr:not(:first-child)>td:nth-child(3),.owner-panel .page>table.table>tbody>tr:not(:first-child)>td:nth-child(6){grid-column:1/-1}
  .owner-panel .page>table.table>tbody>tr:not(:first-child)>td:nth-child(4),.owner-panel .page>table.table>tbody>tr:not(:first-child)>td:nth-child(5){min-height:68px}
  .owner-panel .page>table.table>tbody>tr:not(:first-child)>td:last-child{border-bottom:0!important}
  .owner-panel .page>table.table .acts{display:grid;grid-template-columns:1fr;gap:8px}
  .owner-panel .page>table.table .acts form{margin:0}
  .owner-panel .page>table.table .acts .btn{display:flex;align-items:center;justify-content:center;width:100%;min-height:44px;box-sizing:border-box;white-space:normal;text-align:center}
  .owner-panel .page>table.table .acts a[href*="apo-private-call"]{background:#17784d!important;color:#fff!important;font-weight:800}
}
.owner-nav-subgroup summary{cursor:pointer;list-style:none;padding:10px 14px;color:#526057;font-weight:800}.owner-nav-subgroup a{display:block;padding:9px 24px;color:#526057;text-decoration:none}.owner-nav-subgroup[open]{border-radius:12px;background:#faf8f2}
</style>
@endif
<style>
#hotel-call-confirm{width:min(460px,calc(100% - 24px));max-width:none;margin:auto;padding:0;overflow:hidden;overscroll-behavior:contain;border:1px solid #d6e5df;border-radius:22px;background:#fff;color:#28372e;box-shadow:0 24px 70px rgba(24,35,28,.28)}
#hotel-call-confirm::backdrop{background:rgba(22,31,25,.58);backdrop-filter:blur(3px)}
.hotel-call-confirm-content{display:flex;align-items:flex-start;gap:15px;padding:25px 25px 18px}
.hotel-call-confirm-icon{display:grid;place-items:center;flex:0 0 48px;height:48px;border:1px solid #cfe6e2;border-radius:16px;background:#e8f4f2;color:#0e7c86;font-size:24px;font-weight:800}
.hotel-call-confirm-copy h2{margin:1px 0 7px;color:#29382f;font-size:20px;line-height:1.25}
.hotel-call-confirm-copy p{margin:0;color:#3b5557;font-size:14px;font-weight:600;line-height:1.65}
.hotel-call-confirm-actions{display:flex;justify-content:flex-end;gap:9px;padding:0 25px 22px}
.hotel-call-confirm-actions button{min-height:44px;padding:10px 16px;border:1px solid #e5e9e4;border-radius:12px;background:#f7f9f7;color:#435348;font:inherit;font-weight:750;cursor:pointer}
.hotel-call-confirm-actions [data-call-continue]{border-color:#0e7c86;background:#0e7c86;color:#fff}
.hotel-call-confirm-actions button:focus-visible{outline:3px solid #e6b35c;outline-offset:3px}
@media(max-width:480px){.hotel-call-confirm-content{gap:12px;padding:21px 19px 16px}.hotel-call-confirm-icon{flex-basis:42px;height:42px;border-radius:14px}.hotel-call-confirm-copy h2{font-size:18px}.hotel-call-confirm-copy p{font-size:13px}.hotel-call-confirm-actions{padding:0 19px 19px}.hotel-call-confirm-actions button{flex:1;padding-inline:10px}}
</style>
<dialog id="hotel-call-confirm" aria-labelledby="hotel-call-confirm-title" aria-describedby="hotel-call-confirm-message">
  <div class="hotel-call-confirm-content"><span class="hotel-call-confirm-icon" data-contact-icon aria-hidden="true">☎</span><div class="hotel-call-confirm-copy"><h2 id="hotel-call-confirm-title">Aramadan önce küçük bir not</h2><p id="hotel-call-confirm-message">Ararken işletmeye “Antalya Pet Otel üzerinden ulaşıyorum” diyebilirsiniz. Böylece bizi nereden bulduğunuzu belirtmiş olursunuz.</p></div></div>
  <div class="hotel-call-confirm-actions"><button type="button" data-call-cancel>Vazgeç</button><button type="button" data-call-continue>Aramayı başlat</button></div>
</dialog>
<script>
(function(){
  var dialog=document.getElementById('hotel-call-confirm');
  if(!dialog)return;
  var target='',isWhatsApp=false,targetSlug='';
  function hotelSlugOf(el){var c=el.closest('[data-hotel-slug]');if(c)return c.getAttribute('data-hotel-slug')||'';var p=document.querySelector('[data-hotel-page]');return p?(p.getAttribute('data-hotel-page')||''):'';}
  function trackContact(whatsApp,slug){if(!slug||navigator.doNotTrack==='1')return;try{var d=new FormData();d.append('hotel',slug);d.append('action',whatsApp?'whatsapp':'phone');var u=@json(route('analytics.contact'));if(navigator.sendBeacon)navigator.sendBeacon(u,d);else fetch(u,{method:'POST',body:d,keepalive:true});}catch(e){}}
  var title=dialog.querySelector('#hotel-call-confirm-title');
  var message=dialog.querySelector('#hotel-call-confirm-message');
  var icon=dialog.querySelector('[data-contact-icon]');
  var continueButton=dialog.querySelector('[data-call-continue]');
  function openContact(){var href=target,whatsApp=isWhatsApp,slug=targetSlug;targetSlug='';if(href)trackContact(whatsApp,slug);target='';isWhatsApp=false;dialog.close();if(!href)return;if(whatsApp){var opened=window.open(href,'_blank');if(opened)opened.opener=null;}else window.location.href=href;}
  document.addEventListener('click',function(event){
    var link=event.target.closest&&event.target.closest('a[data-hotel-phone-call],a.hotel-whatsapp-link,a.otel-whatsapp-action,a[data-room-whatsapp],a.hotel-quick-action.hotel-whatsapp,a.apt-lcard__quick[href^="https://wa.me/"]');
    if(!link)return;
    event.preventDefault();
    target=link.href;targetSlug=hotelSlugOf(link);
    isWhatsApp=!link.matches('[data-hotel-phone-call]');
    title.textContent=isWhatsApp?'WhatsApp’tan yazmadan önce küçük bir not':'Aramadan önce küçük bir not';
    message.textContent=isWhatsApp?'WhatsApp’tan yazarken işletmeye “Antalya Pet Otel üzerinden ulaşıyorum” diyebilirsiniz. Böylece bizi nereden bulduğunuzu belirtmiş olursunuz.':'Ararken işletmeye “Antalya Pet Otel üzerinden ulaşıyorum” diyebilirsiniz. Böylece bizi nereden bulduğunuzu belirtmiş olursunuz.';
    icon.textContent=isWhatsApp?'✉':'☎';
    continueButton.textContent=isWhatsApp?'WhatsApp’ı aç':'Aramayı başlat';
    if(typeof dialog.showModal==='function')dialog.showModal();
    else if(window.confirm(message.textContent+' Devam etmek istiyor musunuz?'))openContact();
  });
  dialog.querySelector('[data-call-cancel]').addEventListener('click',function(){target='';isWhatsApp=false;dialog.close();});
  continueButton.addEventListener('click',openContact);
  dialog.addEventListener('click',function(event){if(event.target===dialog){target='';isWhatsApp=false;dialog.close();}});
  dialog.addEventListener('cancel',function(){target='';isWhatsApp=false;});
})();
</script>
@if(!request()->routeIs('panel.*') && !request()->routeIs('admin.*'))
@include('partials.site-assistant')
@endif
@if($analyticsId && $analyticsPageAllowed)
@include('components.analytics-consent', ['consent' => $analyticsConsent])
@endif
@include('admin.live-edit')
</body>
</html>







