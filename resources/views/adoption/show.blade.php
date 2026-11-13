@extends('layouts.app')
@section('title',$listing->pet_name.' için yuva aranıyor')
@section('content')
<div class="wrap page">
  <a class="adopt-back" href="{{ route('adoption.index') }}">← Sahiplendirme ilanları</a>
  <article class="cardbox adopt-detail">
    <div class="adopt-detail-photo">@if($listing->photo_path)<img src="{{ route('adoption.photo',$listing) }}" alt="{{ $listing->pet_name }} için sahiplendirme fotoğrafı">@else{{ $listing->species==='cat'?'🐱':($listing->species==='dog'?'🐶':'🐾') }}@endif</div>
    <div class="adopt-detail-copy">
      <span class="eyebrow">{{ $listing->species==='cat'?'KEDİ':($listing->species==='dog'?'KÖPEK':'DOSTUMUZ') }} · YUVA ARIYOR</span>
      <h1>{{ $listing->pet_name }}</h1>
      <div class="adopt-detail-facts"><span class="chip">{{ $listing->breed ?: 'Irkı belirtilmemiş' }}</span><span class="chip">{{ $listing->age_label }}</span><span class="chip">{{ $listing->sex_label ?? 'Cinsiyet belirtilmemiş' }}</span><span class="chip">{{ $listing->vaccinated?'İlan sahibinin beyanına göre aşılı':'Aşı durumu belirtilmemiş' }}</span></div>
      <p>📍 {{ $listing->district ? $listing->district.', ' : '' }}{{ $listing->city }}</p>
      <div class="adopt-description">{{ $listing->description }}</div>
      <section class="cardbox adopt-contact"><b>İlanı veren üyeyle iletişim</b>
        @if($listing->user)
          <p>{{ $listing->user->name }}</p>
          @if($listing->contact_phone)<a class="btn" href="tel:{{ preg_replace('/[^0-9+]/','',$listing->contact_phone) }}">İlan sahibinin beyan ettiği telefon · Ara</a>@else<p>İlan sahibi telefon numarası paylaşmadı.</p>@endif
        @else
          <p>İlanı veren hesabı artık bulunamadığı için iletişim bilgisi gösterilemiyor.</p>
        @endif
        <p class="adopt-contact-note">Peti teslim almadan önce ilanı veren kişiyle yüz yüze tanışın; sağlık ve aşı bilgilerini doğrulayın. Şüpheli bir durumla karşılaşırsanız ilanı site yönetimine bildirin.</p>
      </section>
    </div>
  </article>
</div>
@endsection
