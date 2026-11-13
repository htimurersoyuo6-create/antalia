@extends('layouts.app')
@section('title', 'Tesis başvurularım | Antalya Pet Otel')
@section('robots', 'noindex,nofollow')
@section('content')
<main class="wrap partner-listing-page">
  <nav class="partner-breadcrumb" aria-label="Sayfa yolu"><a href="{{ route('home') }}">Ana sayfa</a><span>/</span>@if(auth()->check())<a href="{{ route('panel.index') }}">İşletme paneli</a>@else<a href="{{ route('partner.listing.create') }}">Tesis başvurusu</a>@endif<span>/</span><span>Tesis başvurularım</span></nav>
  <section class="partner-listing-card">
    <span class="partner-eyebrow"><i></i> İŞLETME BAŞVURULARI</span>
    <h1>{{ request()->query('t') ? 'Tesis başvurunuzun durumu' : 'Ücretsiz tesis başvurularım' }}</h1>
    <p class="partner-listing-intro">{{ request()->query('t') ? 'Başvurunuzun güncel durumunu buradan izleyebilirsiniz. Bu kişiye özel bağlantı 6 ay boyunca geçerlidir; güvenli bir yerde saklayın.' : 'Bu hesap e-posta adresiyle gönderilmiş ücretsiz tesis tanıtım talepleri burada listelenir. Başvurunuzun güncel durumu burada gösterilir.' }}</p>
    @if(session('ok'))<div class="partner-listing-success" role="status"><b>Başvurunuz alındı</b><span>{{ session('ok') }}</span></div>@endif
    @forelse($applications as $application)
      <article class="partner-status-item"><div><b>{{ $application->hotel_name }}</b><small>Gönderim: {{ $application->created_at?->format('d.m.Y H:i') }}</small></div><span class="partner-status-badge status-{{ $application->listing_status ?? 'received' }}">{{ $application->listing_status_label }}</span>@if(($application->listing_status ?? '')==='need_info' && $application->listing_note)<small class="partner-status-note"><b>İstenen bilgi:</b> {{ $application->listing_note }}</small>@endif</article>
    @empty
      <div class="partner-listing-success"><b>Henüz başvuru yok</b><span>Ücretsiz tesis başvuru formunu gönderdiğinizde burada görüntüleyebilirsiniz.</span></div>
    @endforelse
    @if(request()->query('t'))<a class="partner-listing-status-link" href="{{ route('partner.listing.status') }}">Takip bağlantısını yeniden gönder →</a>@elseif(!auth()->check())<a class="partner-listing-status-link" href="{{ route('partner.listing.create') }}">Yeni ücretsiz tesis başvurusu →</a>@endif
  </section>
</main>
@endsection
