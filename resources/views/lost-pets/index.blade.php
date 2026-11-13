@extends('layouts.app')
@section('title', 'Kayıp Pet İlanları | '.\App\Models\Setting::al('site_name', config('app.name')))
@section('desc', 'Sahiplerinin herkese açık paylaşmayı seçtiği aktif kayıp pet ilanları.')
@section('robots', 'noindex, follow')
@section('canonical', route('lost-pets.index'))
@section('content')
<main class="wrap pet-health-page">
  <a class="pet-health-back" href="{{ route('home') }}">← Ana sayfa</a>
  <header class="pet-health-hero">
    <div><span class="pet-health-icon is-orange">!</span><h1>Kayıp pet ilanları</h1><p>Yalnızca sahibinin herkese açık paylaşmayı seçtiği aktif ilanlar gösterilir. Konumlar yaklaşık olarak belirtilir.</p></div>
  </header>
  <section class="pet-health-grid" aria-label="Aktif kayıp pet ilanları">
    @forelse($alerts as $alert)
      @php
        $approxLat = number_format(round((float) $alert->lat, 2), 2, '.', '');
        $approxLng = number_format(round((float) $alert->lng, 2), 2, '.', '');
      @endphp
      <article class="pet-health-card">
        <div class="pet-health-card-head">
          <span class="pet-health-icon is-orange">!</span>
          <div><h2>{{ $alert->pet->name }} aranıyor</h2><p>{{ $alert->pet->species_label }}@if($alert->pet->breed) · {{ $alert->pet->breed }}@endif</p></div>
        </div>
        @if($alert->public_note)<p>{{ $alert->public_note }}</p>@endif
        <p class="pet-health-privacy">Yaklaşık konum: {{ $approxLat }}, {{ $approxLng }} · {{ $alert->reported_at?->format('d.m.Y') }}</p>
        <a class="btn sm" href="{{ route('lost-pets.show', $alert) }}">İlanı görüntüle</a>
      </article>
    @empty
      <div class="pet-health-card"><p class="pet-health-empty">Şu anda herkese açık aktif bir kayıp pet ilanı yok.</p><a href="{{ route('register') }}">Kayıp pet bildirimi oluşturmak için üye olun</a></div>
    @endforelse
  </section>
  <div class="mt-4">{{ $alerts->links() }}</div>
</main>
@endsection
