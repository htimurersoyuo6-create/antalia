<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>@yield('title', 'Yönetim') · Pet Otel</title>
<link rel="stylesheet" href="{{ asset('css/admin.css') }}?v={{ @filemtime(public_path('css/admin.css')) }}">
<link rel="stylesheet" href="{{ asset('css/panel-readability.css') }}?v={{ @filemtime(public_path('css/panel-readability.css')) }}-sidebar-mobile-v3">
</head>
<body class="a-body">
@php
$ic = [
 'grid' => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>',
 'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
 'building' => '<path d="M4 21V5a1 1 0 0 1 1-1h8a1 1 0 0 1 1 1v16"/><path d="M14 10h5a1 1 0 0 1 1 1v10"/><path d="M8 8h2M8 12h2M8 16h2M3 21h18"/>',
 'file' => '<path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5M9 13h6M9 17h6"/>',
 'users' => '<circle cx="9" cy="8" r="3"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0M16 5a3 3 0 0 1 0 6M18 14a6 6 0 0 1 3.5 5.5"/>',
 'mail' => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/>',
 'out' => '<path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4M10 17l5-5-5-5M15 12H3"/>',
 'star' => '<path d="m12 3 2.7 5.6 6.1.9-4.4 4.3 1 6.1L12 17l-5.4 2.9 1-6.1L3.2 9.5l6.1-.9z"/>',
 'cog' => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z"/>',
 'ext' => '<path d="M14 4h6v6M20 4l-9 9M18 14v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h5"/>',
];
$svg = fn($n) => '<svg class="a-ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.$ic[$n].'</svg>';
$home = route('admin.index');
$navPending = \App\Models\Hotel::where('status','pending')->count();
$navClaims = \App\Models\HotelClaim::where('status','pending')->count();
@endphp
<aside class="a-side">
  <a class="a-brand" href="{{ $home }}"><span class="a-logo">🐾</span><span>Pet Otel<small>Yönetim Paneli</small></span></a>
  <button class="a-menu-toggle" type="button" aria-expanded="false" aria-controls="admin-nav">☰ Menü</button>
  <nav class="a-nav" id="admin-nav">
    <div class="a-nav-t">Genel</div>
    <a href="{{ route('admin.index') }}" class="{{ request()->routeIs('admin.index') ? 'on' : '' }}">{!! $svg('grid') !!}Genel bakış</a>
    <a href="{{ route('admin.statistics') }}" class="{{ request()->routeIs('admin.statistics') ? 'on' : '' }}">{!! $svg('grid') !!}İstatistikler</a>
    <a href="{{ route('admin.bi.index') }}" class="{{ request()->routeIs('admin.bi.index') ? 'on' : '' }}">{!! $svg('grid') !!}İş zekâsı</a>
    <a href="{{ route('admin.partner-settlements.index') }}" class="{{ request()->routeIs('admin.partner-settlements.*') ? 'on' : '' }}">{!! $svg('grid') !!}Partner mutabakatı</a>

    <div class="a-nav-t">Rezervasyon ve ilişkiler</div>
    <a href="{{ route('admin.hotel-chats') }}" class="{{ request()->routeIs('admin.hotel-chats') ? 'on' : '' }}">{!! $svg('mail') !!}Özel sohbetler</a>
    <a href="{{ route('admin.crm') }}" class="{{ request()->routeIs('admin.crm') ? 'on' : '' }}">{!! $svg('users') !!}CRM ve misafir ilişkileri</a>
    <a href="{{ route('admin.reservations.index') }}" class="{{ request()->routeIs('admin.reservations.*') ? 'on' : '' }}">{!! $svg('clock') !!}Rezervasyonlar</a>
    <a href="{{ route('admin.users.index') }}" class="{{ request()->routeIs('admin.users.*') ? 'on' : '' }}">{!! $svg('users') !!}Müşteriler ve işletmeler</a>
    <div class="a-nav-t">Finans ve faturalar</div>
    <a href="{{ route('admin.invoices.index') }}" class="{{ request()->routeIs('admin.invoices.*') ? 'on' : '' }}">{!! $svg('file') !!}Rezervasyon faturaları</a>
    <a href="{{ route('admin.subscriptions.index') }}" class="{{ request()->routeIs('admin.subscriptions.*') ? 'on' : '' }}">{!! $svg('file') !!}Abonelik ve planlar</a>
    <div class="a-nav-t">Tesis yönetimi</div>
    <a href="{{ route('admin.applications') }}" class="{{ request()->routeIs('admin.applications') ? 'on' : '' }}">{!! $svg('clock') !!}Başvurular @if($navPending)<em>{{ $navPending }}</em>@endif</a>
    <a href="{{ route('admin.claims.index') }}" class="{{ request()->routeIs('admin.claims.*') ? 'on' : '' }}">{!! $svg('building') !!}Sahiplenme talepleri @if($navClaims)<em>{{ $navClaims }}</em>@endif</a>
    <a href="{{ route('admin.hotels.index') }}" class="{{ request()->routeIs('admin.hotels.*') ? 'on' : '' }}">{!! $svg('building') !!}Oteller</a>
    <div class="a-nav-t">İçerik ve topluluk</div>
    <a href="{{ route('admin.posts.index') }}" class="{{ request()->routeIs('admin.posts.*') ? 'on' : '' }}">{!! $svg('file') !!}Blog yazıları</a>
    <a href="{{ route('admin.pages.index') }}" class="{{ request()->routeIs('admin.pages.*') ? 'on' : '' }}">{!! $svg('file') !!}Bilgi sayfaları</a>
    <a href="{{ route('admin.media') }}" class="{{ request()->routeIs('admin.media*') ? 'on' : '' }}">{!! $svg('file') !!}Medya kütüphanesi</a>
    <a href="{{ route('admin.messages') }}" class="{{ request()->routeIs('admin.messages') ? 'on' : '' }}">{!! $svg('mail') !!}Mesajlar</a>
    <a href="{{ route('admin.reviews.index') }}" class="{{ request()->routeIs('admin.reviews.*') ? 'on' : '' }}">{!! $svg('star') !!}Yorumlar</a>
    <a href="{{ route('admin.adoption.queue') }}" class="{{ request()->routeIs('admin.adoption.*') ? 'on' : '' }}">🐾 Sahiplendirme onayı</a>
    <a href="{{ route('admin.forum.queue') }}" class="{{ request()->routeIs('admin.forum.*') ? 'on' : '' }}">▤ Forum moderasyonu</a>
    <a href="{{ route('admin.pet-social.moderation') }}" class="{{ request()->routeIs('admin.pet-social.*') ? 'on' : '' }}">📸 Pet Akışı moderasyonu</a>
    <a href="{{ route('admin.menus') }}" class="{{ request()->routeIs('admin.menus*') ? 'on' : '' }}">{!! $svg('file') !!}Menüler ve altbilgi</a>
    <div class="a-nav-t">Sistem</div>
    <a href="{{ route('admin.ai-test') }}" class="{{ request()->routeIs('admin.ai-test*') ? 'on' : '' }}">{!! $svg('grid') !!}Yerel AI testi</a>
    <a href="{{ route('admin.settings') }}" class="{{ request()->routeIs('admin.settings*') ? 'on' : '' }}">{!! $svg('cog') !!}Site ayarları</a>
  </nav>
  <div class="a-side-f">
    <a href="{{ url('/') }}" target="_blank" rel="noopener">{!! $svg('ext') !!}Siteyi görüntüle</a>
    <div class="a-user"><span class="a-av">{{ mb_strtoupper(mb_substr(auth()->user()->name,0,1)) }}</span><div><b>{{ auth()->user()->name }}</b><small>{{ auth()->user()->email }}</small></div></div>
    <form method="post" action="{{ route('logout') }}">@csrf<button class="a-out">{!! $svg('out') !!}Çıkış yap</button></form>
  </div>
</aside>
<main class="a-main">
  <header class="a-top"><div><h1>@yield('heading', 'Yönetim')</h1><p>@yield('sub')</p></div><div class="a-top-r">@yield('actions')@include('components.panel-search')</div></header>
  @if(session('ok'))<div class="a-flash ok">{{ session('ok') }}</div>@endif
  @if($errors->any())<div class="a-flash err">@foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>@endif
  @yield('content')
</main>

<script>
(function () {
  var legacy = {
    '#bekleyen': @json(route('admin.applications')),
    '#sahiplenme': @json(route('admin.claims.index')),
    '#sahiplenmeler': @json(route('admin.claims.index')),
    '#oteller': @json(route('admin.hotels.index')),
    '#kullanicilar': @json(route('admin.users.index')),
    '#blog': @json(route('admin.posts.index')),
    '#mesajlar': @json(route('admin.messages')),
    '#yorumlar': @json(route('admin.reviews.index'))
  };
  var dashboardPath = @json(parse_url(route('admin.index'), PHP_URL_PATH));
  var target = legacy[window.location.hash];
  if (target && window.location.pathname.replace(/\/$/, '') === dashboardPath.replace(/\/$/, '')) {
    window.location.replace(target + window.location.search);
  }
})();
</script>
<script>
(function(){var b=document.querySelector('.a-menu-toggle'),s=document.querySelector('.a-side');if(!b||!s)return;b.addEventListener('click',function(){var open=s.classList.toggle('is-open');b.setAttribute('aria-expanded',open?'true':'false');});})();
</script>
@include('components.activity-sound')
</body>
</html>
