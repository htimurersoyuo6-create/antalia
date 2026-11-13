@extends('layouts.app')
@section('title', 'İşletme Merkezi | Antalya Pet Otel')
@section('desc', 'Rezervasyonlarınızı, misafirlerinizi ve tesislerinizi tek ekrandan yönetin.')
@section('content')
<div class="owner-dashboard wrap">
  <section class="owner-welcome">
    <div class="owner-welcome-copy">
      <span class="owner-eyebrow">İŞLETME MERKEZİ</span>
      <h1>Hoş geldiniz, {{ auth()->user()->name }}</h1>
      <p>Rezervasyonları takip edin, misafirlerinize yanıt verin ve tesislerinizi kolayca yönetin.</p>
      <div class="owner-welcome-actions">
        <a class="owner-primary-action" href="{{ route('panel.reservations') }}"><span aria-hidden="true">▣</span> Rezervasyonları görüntüle</a>
        <a class="owner-secondary-action" href="{{ route('panel.hotel-chats') }}"><span aria-hidden="true">✉</span> Müşteri sohbetleri</a>
      </div>
    </div>
    <div class="owner-welcome-mark" aria-hidden="true"><span>🐾</span><small>Misafir deneyimi<br>burada başlar</small></div>
  </section>

  @unless($canUseLive)
    <aside class="owner-demo-banner"><span aria-hidden="true">◈</span><div><b>Demo görünümü açık</b><p>Paneli ve işletme araçlarını inceleyebilirsiniz. Gerçek tesis, oda, rezervasyon ve müşteri işlemleri ücretli aboneliğiniz etkinleşince açılır.</p></div><a href="{{ route('panel.subscriptions') }}">Abonelik planlarını incele <span aria-hidden="true">→</span></a></aside>
  @endunless

  @foreach($hotels as $hotel)
    @php($subscription = $latestSubscriptions->get($hotel->id))
    @if($hotel->subscription_suspended || $subscription?->status === 'expired')
      <aside class="owner-subscription-alert expired"><span aria-hidden="true">!</span><div><b>{{ $hotel->name }} · abonelik süresi doldu</b><p>Tesisiniz arama sonuçlarından geçici olarak kaldırıldı. Aboneliği yenileyip ödeme doğrulandığında otomatik yayına alınır.</p></div><a href="{{ route('panel.subscriptions') }}">Aboneliği yenile →</a></aside>
    @elseif($subscription && in_array($subscription->status, ['awaiting_payment','pending_review'], true))
      <aside class="owner-subscription-alert"><span aria-hidden="true">₺</span><div><b>{{ $hotel->name }} · abonelik ödemesi bekleniyor</b><p>{{ $subscription->requested_payment_method === 'card' ? 'Kart ödemenizi tamamlayın; gerçek tahsilat doğrulanınca hesabınız otomatik açılır.' : 'Havale/EFT sonrası ödeme referansı yönetimce doğrulanınca hesabınız etkinleşir.' }}</p></div><a href="{{ route('panel.subscriptions') }}">Ödeme durumunu görüntüle →</a></aside>
    @endif
  @endforeach

  @if($overdueInvoices > 0)<aside class="owner-subscription-alert expired"><span aria-hidden="true">▤</span><div><b>{{ $overdueInvoices }} faturada 7 günlük süre aşıldı</b><p>Ödemesi alınmış rezervasyonların faturalarını müşterilere iletin.</p></div><a href="{{ route('panel.invoices',['status'=>'pending']) }}">Faturaları incele →</a></aside>@endif
<section class="owner-section" aria-labelledby="owner-overview-title">
    <div class="owner-section-heading"><div><span class="owner-eyebrow">BUGÜNÜN ÖZETİ</span><h2 id="owner-overview-title">Rezervasyon durumunuz</h2></div><a href="{{ route('panel.reservations') }}">Tüm rezervasyonlar <span aria-hidden="true">→</span></a></div>
    <div class="owner-stat-grid">
      <a class="owner-stat-card pending" href="{{ route('panel.reservations', ['durum' => 'pending']) }}"><span class="owner-stat-icon" aria-hidden="true">◷</span><span class="owner-stat-label">Onay bekleyen</span><strong>{{ $stats['pending'] }}</strong><small>İşlem bekleyen talepler</small></a>
      <a class="owner-stat-card upcoming" href="{{ route('panel.reservations', ['durum' => 'confirmed']) }}"><span class="owner-stat-icon" aria-hidden="true">▦</span><span class="owner-stat-label">Yaklaşan konaklamalar</span><strong>{{ $stats['upcoming'] }}</strong><small>Onaylanmış rezervasyonlar</small></a>
      <a class="owner-stat-card inhouse" href="{{ route('panel.reservations') }}"><span class="owner-stat-icon" aria-hidden="true">♥</span><span class="owner-stat-label">Şu an tesisinizde</span><strong>{{ $stats['in_house'] }}</strong><small>Devam eden konaklamalar</small></a>
    </div>
  </section>

  <section class="owner-section" aria-labelledby="owner-today-title">
    <div class="owner-section-heading"><div><span class="owner-eyebrow">GÜNLÜK OPERASYON</span><h2 id="owner-today-title">Bugünün giriş ve çıkışları</h2></div><span class="owner-date">{{ now()->translatedFormat('l, j F') }}</span></div>
    <div class="owner-today-grid">
      @foreach([['giris', 'Bugün giriş', 'Giriş yapacak misafirler', '↘', 'checkin'], ['cikis', 'Bugün çıkış', 'Çıkış yapacak misafirler', '↗', 'checkout'], ['icerde', 'Konaklayan dostlar', 'Tesisinizde konaklıyor', '♡', 'inhouse']] as [$k, $title, $caption, $icon, $tone])
        <article class="owner-today-card {{ $tone }}">
          <header><span class="owner-today-icon" aria-hidden="true">{{ $icon }}</span><div><h3>{{ $title }}</h3><p>{{ $caption }}</p></div><b>{{ $bugun[$k]->count() }}</b></header>
          @forelse($bugun[$k] as $rz)
            <div class="owner-today-guest"><div><strong>{{ $rz->pet->name }}</strong><small>{{ $rz->user->name }}@if($rz->user->phone) · {{ $rz->user->phone }}@endif</small></div><span>{{ $rz->room->name }}</span></div>
          @empty
            <p class="owner-today-empty">Bugün için kayıt bulunmuyor.</p>
          @endforelse
        </article>
      @endforeach
    </div>
  </section>

  <section class="owner-section" aria-labelledby="owner-hotels-title">
    <div class="owner-section-heading"><div><span class="owner-eyebrow">TESİSLERİNİZ</span><h2 id="owner-hotels-title">İşletme ve oda yönetimi</h2></div><a href="{{ route('panel.hotel.create') }}">Tesis ekle <span aria-hidden="true">→</span></a></div>
    @forelse($hotels as $h)
      @php($hotelStatus = ['pending' => 'İnceleme bekliyor', 'active' => 'Yayında', 'suspended' => 'Askıya alınmış'][$h->status] ?? 'Durum bilgisi')
      <article class="owner-hotel-card">
        <div class="owner-hotel-symbol" aria-hidden="true">⌂</div>
        <div class="owner-hotel-info"><div class="owner-hotel-title"><h3>{{ $h->name }}</h3><span class="owner-hotel-status {{ $h->status }}">{{ $hotelStatus }}</span></div><p>{{ $h->rooms_count }} oda tipi <span aria-hidden="true">·</span> Oda ve müsaitlik yönetimi</p></div>
        <div class="owner-hotel-actions"><a class="owner-secondary-action" href="{{ route('panel.hotel.edit', $h) }}">Tesis ve odalar</a>@if($h->status==='active')<a class="owner-text-action" href="{{ route('hotels.show',$h->slug) }}">İlanı görüntüle <span aria-hidden="true">↗</span></a>@endif</div>
        <div class="owner-profile-progress"><div><b>İlan tamamlama</b><strong>{{ $h->profile_completion['percent'] }}%</strong></div><div class="owner-profile-progressbar"><i style="width:{{ $h->profile_completion['percent'] }}%"></i></div><p>@foreach($h->profile_completion['steps'] as $step)<span class="{{ $step['done']?'done':'todo' }}">{{ $step['done']?'✓':'○' }} {{ $step['label'] }}</span>@endforeach</p>
        @if($h->profile_completion['demo_cover'])<small class="owner-profile-demo-warning">Kapak görseliniz temsili. İlan güveni için gerçek tesis fotoğrafı yükleyin.</small>@endif
        @if($h->room_info_updated_at)<small class="{{ $h->room_info_updated_at->lt(now()->subDays(30))?'stale':'' }}">Oda/fiyat bilgisi son güncelleme: {{ $h->room_info_updated_at->format('d.m.Y') }}@if($h->room_info_updated_at->lt(now()->subDays(30))) · Bilgileri kontrol edin @endif</small>@else<small>Oda ve fiyat bilgisi ekleyin.</small>@endif
        @if($h->rooms_count)
          @if($h->room_info_is_confirmed)
            <small class="owner-room-confirmed">✓ Oda ve fiyat bilgileri {{ $h->room_info_confirmed_at->format('d.m.Y') }} tarihinde doğrulandı.</small>
          @elseif($h->room_info_confirmation_status === 'changed')
            <small class="stale">Oda/fiyat bilgileri son doğrulamadan sonra değişti; misafir sayfasında güncelliği belirsiz gösterilir.</small>
          @elseif($h->room_info_confirmation_status === 'stale')
            <small class="stale">Oda/fiyat teyidi 30 günden eski; bilgileri yeniden kontrol edin.</small>
          @else
            <small class="stale">Oda/fiyat bilgileri henüz işletme tarafından doğrulanmadı.</small>
          @endif
          <form class="owner-room-confirm-form" method="post" action="{{ route('panel.hotel.confirm-room-info', $h) }}">@csrf<button type="submit" class="owner-room-confirm-button">✓ Bilgileri kontrol edip doğrula</button></form>
        @endif</div>
      </article>
    @empty
      <div class="owner-empty-hotels"><span aria-hidden="true">⌂</span><div><h3>Henüz kayıtlı tesis yok</h3><p>Ücretli aboneliğiniz etkinleştiğinde tesisinizi, oda tiplerinizi ve rezervasyon kabul bilgilerinizi buradan yönetebilirsiniz.</p></div><a class="owner-primary-action" href="{{ route('panel.hotel.create') }}">Tesis ekle <span aria-hidden="true">→</span></a></div>
    @endforelse
  </section>
</div>
<style>
.owner-dashboard>.owner-section{padding:0!important}
.owner-subscription-alert{display:flex;align-items:center;gap:14px;padding:16px 18px;margin:0 0 12px;border:1px solid #f1dfbf;border-radius:15px;background:#fff9ed;color:#674b21}.owner-subscription-alert>span{display:grid;place-items:center;flex:0 0 38px;height:38px;border-radius:12px;background:#ffedce;color:#a76b10;font-weight:900}.owner-subscription-alert.expired{border-color:#f1cfc3;background:#fff5f1;color:#803d29}.owner-subscription-alert.expired>span{background:#ffe4da;color:#d44926}.owner-subscription-alert div{flex:1}.owner-subscription-alert b{font-size:.9rem}.owner-subscription-alert p{font-size:.8rem;line-height:1.5;margin:3px 0 0;color:#756d60}.owner-subscription-alert a{white-space:nowrap;color:#d94b25;font-size:.8rem;font-weight:800;text-decoration:none}@media(max-width:650px){.owner-subscription-alert{align-items:flex-start;flex-wrap:wrap}.owner-subscription-alert a{margin-left:52px;white-space:normal}}
.owner-profile-progress{grid-column:2/-1;display:grid;gap:7px;padding:12px 14px;border:1px solid #edf0ea;border-radius:12px;background:#fafbf9}.owner-profile-progress>div:first-child{display:flex;justify-content:space-between;color:#526057;font-size:11px}.owner-profile-progress>div:first-child strong{color:#d94b25}.owner-profile-progressbar{height:6px;overflow:hidden;border-radius:99px;background:#e9eee8}.owner-profile-progressbar i{display:block;height:100%;border-radius:inherit;background:linear-gradient(90deg,#f4511e,#36a779)}.owner-profile-progress p{display:flex;flex-wrap:wrap;gap:5px 12px;margin:0}.owner-profile-progress p span,.owner-profile-progress small{color:#879189;font-size:10px}.owner-profile-progress p .done{color:#23865c}.owner-profile-progress small.stale{color:#b84a25;font-weight:800}.owner-profile-progress small.owner-profile-demo-warning{padding:8px 10px;border:1px solid #f4d2bd;border-radius:9px;background:#fff5ed;color:#ad4825;font-size:10px;font-weight:750;line-height:1.45}@media(max-width:650px){.owner-profile-progress{grid-column:1/-1}}
.owner-profile-progress small.owner-room-confirmed{color:#23865c;font-weight:750}.owner-room-confirm-form{margin:0}.owner-room-confirm-button{justify-self:start;padding:7px 10px;border:1px solid #bde3d0;border-radius:9px;background:#f1fbf5;color:#18794e;font:inherit;font-size:10px;font-weight:800;cursor:pointer}.owner-room-confirm-button:hover{background:#e6f7ed}
.owner-demo-banner{display:flex;align-items:center;gap:13px;margin:0 0 24px;padding:15px 17px;border:1px solid #f1dfc8;border-radius:15px;background:linear-gradient(105deg,#fff9ef,#fff);color:#6d5737}.owner-demo-banner>span{display:grid;place-items:center;flex:none;width:38px;height:38px;border-radius:12px;background:#fff0d6;color:#c27a11;font-size:20px}.owner-demo-banner>div{flex:1}.owner-demo-banner b{font-size:12px;font-weight:900}.owner-demo-banner p{margin:3px 0 0;color:#786f60;font-size:11px;line-height:1.55}.owner-demo-banner>a{white-space:nowrap;color:#c24a20;font-size:11px;font-weight:850;text-decoration:none}@media(max-width:650px){.owner-demo-banner{align-items:flex-start;flex-wrap:wrap}.owner-demo-banner>a{margin-left:51px;white-space:normal}}
</style>
@endsection
