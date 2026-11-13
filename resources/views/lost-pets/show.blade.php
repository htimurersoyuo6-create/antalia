@extends('layouts.app')
@section('title', $alert->pet->name.' Kayıp Pet İlanı')
@section('desc', 'Sahibi tarafından paylaşılan aktif kayıp pet ilanı. Konum yaklaşık olarak gösterilir.')
@section('robots', 'noindex, follow')
@section('canonical', route('lost-pets.show', $alert))
@section('ogtype', 'article')
@section('ogtitle', $alert->pet->name.' kayıp pet ilanı')
@section('ogdesc', $alert->public_note ?: 'Aktif kayıp pet ilanı. Yaklaşık konum ve sahibinin paylaşmayı seçtiği iletişim bilgisi.')
@section('content')
<main class="wrap pet-health-page">
  <a class="pet-health-back" href="{{ route('lost-pets.index') }}">← Kayıp pet ilanları</a>
  <header class="pet-health-hero">
    <div><span class="pet-health-icon is-orange">!</span><h1>{{ $alert->pet->name }} aranıyor</h1><p>{{ $alert->pet->species_label }}@if($alert->pet->breed) · {{ $alert->pet->breed }}@endif · {{ $alert->reported_at?->format('d.m.Y H:i') }} tarihinde bildirildi</p></div>
  </header>
  <article class="pet-health-card">
    @if($alert->public_note)<p>{{ $alert->public_note }}</p>@endif
    <h2>Yaklaşık son görülme konumu</h2>
    <p>{{ $approxLat }}, {{ $approxLng }} · Uyarı yarıçapı {{ number_format($alert->radius_m / 1000, 1, ',', '.') }} km</p>
    <p class="pet-health-privacy">Gizlilik için harita konumu yaklaşık 1 km hassasiyetindedir; kesin koordinatlar paylaşılmaz.</p>
    <p><a class="btn sm" href="https://maps.google.com/?q={{ $approxLat }},{{ $approxLng }}" target="_blank" rel="noopener noreferrer">Yaklaşık konumu haritada aç</a></p>
    @if($alert->public_contact_phone)
      <p><a class="btn sm" href="tel:{{ preg_replace('/[^0-9+]/', '', $alert->public_contact_phone) }}">İlan sahibini ara</a></p>
    @else
      <p class="pet-health-privacy">İlan sahibi telefon numarasını herkese açık paylaşmayı seçmedi.</p>
    @endif
    <button class="btn sm" type="button" onclick="if (navigator.share) { navigator.share({title: document.title, url: location.href}); } else if (navigator.clipboard) { navigator.clipboard.writeText(location.href).then(() => alert('İlan bağlantısı kopyalandı.')); }">İlanı paylaş</button>
  </article>
</main>
@endsection
