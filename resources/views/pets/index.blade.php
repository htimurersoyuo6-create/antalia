@extends('layouts.app')
@section('title', 'Müşteri Paneli | Antalya Pet Otel')
@section('content')
<div class="wrap page customer-dashboard">
  <section class="customer-welcome">
    <div><span class="customer-eyebrow">MİSAFİR PANELİ</span><h1>Merhaba, {{ auth()->user()->name }} 👋</h1><p>Dostlarınızın profillerini, konaklamalarını ve otellerle iletişiminizi tek yerde yönetin.</p></div>
    <a class="btn" href="{{ route('hotels.all') }}">Otel keşfet <span aria-hidden="true">→</span></a>
  </section>
  <nav class="customer-shortcuts customer-shortcuts-top" aria-label="Hızlı bağlantılar">
    <a href="{{ route('pets.index') }}"><span class="shortcut-icon">🐾</span><span>Evcil hayvanlarım</span><b>→</b></a>
    <a href="{{ route('reservations.index') }}"><span class="shortcut-icon">📅</span><span>Rezervasyonlarım</span><b>→</b></a>
    <a href="{{ route('favorites.index') }}"><span class="shortcut-icon">♡</span><span>Favori otellerim</span><b>→</b></a>
    <a href="{{ route('hotels.all') }}"><span class="shortcut-icon">🔎</span><span>Otel bul</span><b>→</b></a>
  </nav>
  <section class="customer-metrics" aria-label="Hesap özeti">
    <a href="{{ route('pets.index') }}"><span class="metric-icon">🐾</span><b>{{ $stats['pets'] }}</b><span>Pet profilim</span></a>
    <a href="{{ route('reservations.index') }}"><span class="metric-icon">📅</span><b>{{ $stats['active'] }}</b><span>Aktif rezervasyon</span></a>
    <a href="{{ route('reservations.index') }}"><span class="metric-icon">🧳</span><b>{{ $stats['upcoming'] }}</b><span>Yaklaşan konaklama</span></a>
    <a href="{{ route('reservations.index') }}"><span class="metric-icon">🚗</span><b>{{ $stats['transfers'] }}</b><span>Canlı transfer</span></a>
  </section>
  <section class="customer-columns">
    <div class="customer-main">
      <div class="customer-section-head"><div><span class="customer-eyebrow">DOSTLARINIZ</span><h2>Pet profilleri</h2></div><a class="btn sm ghost" href="{{ route('pets.create') }}">＋ Yeni profil</a></div>
      @if($pets->isEmpty())
      <div class="customer-empty"><span>🐶</span><h3>İlk dostunuzun profilini ekleyin</h3><p>Bir kez kaydedin; rezervasyonlarınızda aşı ve bakım bilgileri hazır olsun.</p><a class="btn" href="{{ route('pets.create') }}">Pet profili oluştur</a></div>
      @else
      <div class="customer-pet-grid">@foreach($pets as $p)
        <article class="customer-pet-card"><div class="pet-avatar">{{ $p->species === 'cat' ? '🐈' : ($p->species === 'dog' ? '🐕' : '🐾') }}</div><div class="pet-card-body"><h3>{{ $p->name }}</h3><p>{{ $p->species_label }} · {{ $p->size_label }}{{ $p->breed ? ' · '.$p->breed : '' }}</p><span class="pet-vaccine {{ $p->vaccinated ? 'is-ok' : '' }}">{{ $p->vaccinated ? 'Aşı bilgisi tamam' : 'Aşı bilgisini ekleyin' }}</span>@if($p->vaccinated && $p->vaccination_expires_at)<small class="pet-vaccine-date {{ $p->vaccination_expires_at->isBefore(today()) ? 'is-expired' : '' }}">{{ $p->vaccination_expires_at->isBefore(today()) ? 'Aşı tarihi geçti' : 'Geçerlilik: '.$p->vaccination_expires_at->format('d.m.Y') }}</small>@elseif($p->vaccinated)<small class="pet-vaccine-date">Geçerlilik tarihini ekleyin</small>@endif<div class="pet-card-actions"><a href="{{ route('pets.health',$p) }}">Sağlık ve aşı takvimi <span>→</span></a><a href="{{ route('pets.edit',$p) }}">Profili düzenle</a></div></div></article>
      @endforeach</div>
      @endif
      @if(isset($vaccineReminders) && $vaccineReminders->isNotEmpty())
      <section class="pet-reminder-panel"><div><span class="customer-eyebrow">SAĞLIK TAKVİMİ</span><h3>Aşı hatırlatmaları</h3></div><div class="pet-reminder-list">@foreach($vaccineReminders as $reminder)<a href="{{ route('pets.health',$reminder['pet']) }}" class="pet-reminder {{ $reminder['vaccine']->reminder_state === 'overdue' ? 'is-overdue' : '' }}"><span>♡</span><span><b>{{ $reminder['pet']->name }} · {{ $reminder['vaccine']->vaccine_name }}</b><small>{{ $reminder['vaccine']->reminder_state === 'overdue' ? 'Planlanan tarih geçti' : 'Yaklaşan tarih' }} · {{ $reminder['vaccine']->next_due_at->format('d.m.Y') }}</small></span><strong>›</strong></a>@endforeach</div><small>Hatırlatmalar kayıtlı tarihlere dayanır; uygun uygulama zamanını veterinerinizle doğrulayın.</small></section>
      @endif
      <div class="customer-section-head reservation-head"><div><span class="customer-eyebrow">SON HAREKETLER</span><h2>Rezervasyonlarım</h2></div><a class="customer-text-link" href="{{ route('reservations.index') }}">Tümünü gör →</a></div>
      @forelse($reservations as $booking)
      @php($bookingCallActive = \App\Support\PlatformModules::enabled('private_calls', (int) $booking->hotel_id) && $booking->status === 'confirmed' && $booking->check_in->lte(today()) && $booking->check_out->gte(today()))
      <a class="customer-booking-card" href="{{ route('reservations.show',$booking) }}{{ $bookingCallActive ? '#apo-private-call' : '' }}"><div class="booking-date"><b>{{ $booking->check_in->format('d') }}</b><span>{{ mb_strtoupper($booking->check_in->translatedFormat('M'), 'UTF-8') }}</span></div><div class="booking-info"><b>{{ $booking->hotel->name }}</b><span>{{ $booking->pet->name }} · {{ $booking->room->name }}</span><small>{{ $booking->check_in->format('d.m.Y') }} – {{ $booking->check_out->format('d.m.Y') }} · {{ $booking->nights }} gece</small>@if($bookingCallActive)<small style="color:#17784d;font-weight:700">Sesli ve görüntülü görüşme açık · buraya dokunun</small>@endif</div><div class="booking-meta"><span class="st {{ $booking->status }}">{{ $booking->status_label }}</span>@if($booking->transfer_status==='in_progress')<span class="transfer-live-pill">Transfer canlı</span>@endif<strong>{{ number_format($booking->total_price,0,',','.') }} ₺</strong></div><span class="booking-arrow">›</span></a>
      @empty<div class="customer-empty customer-empty-small"><span>🏡</span><h3>İlk konaklamanızı planlayın</h3><p>Uygun otelleri karşılaştırın, oda ve tarih seçerek talebinizi iletin.</p><a class="btn" href="{{ route('hotels.all') }}">Antalya pet otellerini gör</a></div>
      @endforelse
    </div>
    <aside class="customer-side">
      <div class="customer-help-card"><div class="help-icon">💬</div><h3>Otelinizle iletişimde kalın</h3><p>Rezervasyon detayından canlı mesajlaşın; konaklama sırasında fotoğraf, bakım notu ve kamera bağlantılarını takip edin.</p><a href="{{ route('reservations.index') }}">Mesajlarıma git <span>→</span></a></div>
      <div class="customer-help-card customer-safety-card"><div class="help-icon">🛡️</div><h3>Dostunuz için hazır olun</h3><p>Aşı, beslenme ve özel bakım notlarını profilinize ekleyin. Bu bilgiler yalnızca rezervasyon yaptığınız tesisle paylaşılır.</p><a href="{{ route('pets.create') }}">Pet bilgilerini tamamla <span>→</span></a></div>
    </aside>
  </section>
</div>
@endsection
