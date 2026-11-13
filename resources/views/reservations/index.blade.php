@extends('layouts.app')
@section('title', 'Rezervasyonlarım | Antalya Pet Otel')
@section('desc', 'Rezervasyon durumlarınızı, konaklama tarihlerinizi ve pet otel bilgilerinizi yönetin.')
@section('content')
@php($allCount = $statusCounts->sum())
<div class="wrap page my-reservations">
  <header class="my-reservations-hero">
    <div><span class="customer-eyebrow">MİSAFİR PANELİ</span><h1>Rezervasyonlarım</h1><a class="btn ghost" href="{{ route('points.wallet') }}">PotiPuan cüzdanım</a><p>Konaklama planlarınızı, taleplerinizi ve otelle olan iletişiminizi buradan takip edin.</p></div>
    <div class="my-reservations-actions"><a class="btn" href="{{ route('hotels.all') }}">Yeni otel bul <span aria-hidden="true">→</span></a><a class="btn ghost" href="{{ route('pets.index') }}">Pet profillerim</a></div>
  </header>

  <nav class="reservation-filters" aria-label="Rezervasyon durumuna göre filtrele">
    <a href="{{ route('reservations.index') }}" @if(!request('durum')) aria-current="page" @endif><span>Tümü</span><b>{{ $allCount }}</b></a>
    @foreach($statuses as $key => $label)
      <a href="{{ route('reservations.index',['durum'=>$key]) }}" @if(request('durum')===$key) aria-current="page" @endif><span>{{ $label }}</span><b>{{ $statusCounts->get($key,0) }}</b></a>
    @endforeach
  </nav>

  <section class="my-reservation-list" aria-label="Rezervasyon kayıtları">
    @forelse($reservations as $booking)
      <article class="my-reservation-card">
        <div class="my-reservation-date"><b>{{ $booking->check_in->format('d') }}</b><span>{{ mb_strtoupper($booking->check_in->translatedFormat('M'), 'UTF-8') }}</span><small>{{ $booking->check_in->format('Y') }}</small></div>
        <div class="my-reservation-main">
          <div class="my-reservation-title"><h2>{{ $booking->hotel->name }}</h2><span class="st {{ $booking->status }}">{{ $booking->status_label }}</span></div>
          <p>{{ $booking->pet->name }} <span aria-hidden="true">·</span> {{ $booking->room->name }} @if($booking->roomUnit) <span aria-hidden="true">·</span> {{ $booking->roomUnit->name }} @endif</p>
          <div class="my-reservation-meta"><span>🗓 {{ $booking->check_in->format('d.m.Y') }} → {{ $booking->check_out->format('d.m.Y') }} · {{ $booking->nights }} gece</span><span>Rezervasyon kodu: <b>{{ $booking->code }}</b></span></div>
          <div class="my-reservation-badges">
            <span class="reservation-payment {{ $booking->payment_status==='paid'?'is-paid':'' }}">{{ $booking->payment_status==='paid'?'Ödeme alındı':'Otelde ödenecek' }}</span>
            @if($booking->early_departure_status==='approved')<span class="reservation-alert">Erken çıkış onaylandı · {{ $booking->early_departure_date?->format('d.m.Y') }}</span>@elseif($booking->early_departure_status==='requested')<span class="reservation-alert">Erken çıkış talebi bekliyor</span>@endif
            @if($booking->transfer_status==='in_progress')<span class="reservation-live">Transfer canlı takipte</span>@elseif($booking->transfer_status==='requested')<span class="reservation-transfer">Pet transferi talep edildi</span>@endif
          </div>
        </div>
        <div class="my-reservation-side"><b class="my-reservation-price">{{ number_format((float)$booking->total_price,2,',','.') }} ₺</b><a class="btn sm ghost" href="{{ route('reservations.show',$booking) }}">Detayları aç <span aria-hidden="true">→</span></a></div>
      </article>
    @empty
      <div class="my-reservations-empty"><span aria-hidden="true">🏡</span><h2>{{ request('durum') ? 'Bu durumda rezervasyon yok.' : 'Henüz bir konaklama planınız yok.' }}</h2><p>{{ request('durum') ? 'Tüm rezervasyonlara dönün veya başka bir durum seçin.' : 'Antalya’daki pet otellerini inceleyip dostunuza uygun konaklamayı bulun.' }}</p><div><a class="btn" href="{{ route('hotels.all') }}">Otelleri keşfet <span aria-hidden="true">→</span></a>@if(request('durum'))<a class="btn ghost" href="{{ route('reservations.index') }}">Tüm rezervasyonlar</a>@endif</div></div>
    @endforelse
  </section>
  @if($reservations->hasPages())<div class="my-reservations-pagination">{{ $reservations->links() }}</div>@endif
</div>
@endsection
