@extends('layouts.app')
@section('title','Antalya’da Evcil Hayvan Sahiplendirme')
@section('desc','Antalya’da inceleme sürecinden geçen evcil hayvan sahiplendirme ilanlarını keşfedin veya yeni bir ilan gönderin.')
@section('content')
<div class="wrap page adoption-page">
  <section class="adopt-hero">
    <div class="adopt-hero-copy">
      <span class="eyebrow">YENİ BİR YUVA, GÜZEL BİR BAŞLANGIÇ</span>
      <h1>Sevgi dolu bir dost<br>yuvasını arıyor</h1>
      <p>Antalya’daki sahiplendirme ilanlarını keşfedin. Her ilan yayınlanmadan önce ekibimiz tarafından incelenir.</p>
      <div class="adopt-hero-actions">
        <a class="btn" href="{{ auth()->check()?route('adoption.create'):route('login') }}">Sahiplendirme ilanı ver <span aria-hidden="true">→</span></a>
        <a class="btn ghost" href="{{ auth()->check()?route('adoption.mine'):route('login') }}">İlanlarım</a>
        <span class="adopt-live-count"><strong>{{ number_format($listings->total()) }}</strong> aktif ilan</span>
      </div>
    </div>
    <aside class="adopt-welcome-card">
      <div class="adopt-heart" aria-hidden="true">♡</div>
      <span class="forum-card-label">GÜVENLİ TANIŞMA</span>
      <h2>Yeni yuva, karşılıklı güvenle başlar.</h2>
      <ul>
        <li><span>01</span> Petin ihtiyaçlarını açıkça paylaşın</li>
        <li><span>02</span> Sahiplenmeden önce tanışın</li>
        <li><span>03</span> Sağlık ve aşı kayıtlarını doğrulayın</li>
      </ul>
      <p>İlanlar onaylandıktan sonra herkes tarafından görülebilir.</p>
    </aside>
  </section>

  <form class="cardbox adopt-filter" method="get" role="search">
    <label class="sr-only" for="adopt-q">İlanlarda isim veya ırk ara</label>
    <input id="adopt-q" name="q" value="{{ request('q') }}" placeholder="İsim veya ırk ara">
    <label class="sr-only" for="adopt-species">Hayvan türü</label>
    <select id="adopt-species" name="species">
      <option value="">Tüm dostlar</option>
      <option value="cat" @selected(request('species')==='cat')>Kediler</option>
      <option value="dog" @selected(request('species')==='dog')>Köpekler</option>
      <option value="other" @selected(request('species')==='other')>Diğer dostlar</option>
    </select>
    <label class="sr-only" for="adopt-city">Şehir</label>
    <input id="adopt-city" name="city" value="{{ request('city') }}" placeholder="Şehir">
    <button class="btn" type="submit">İlanları bul <span aria-hidden="true">→</span></button>
  </form>

  <div class="adopt-section-heading">
    <div><span class="eyebrow">YUVA ARAYAN DOSTLAR</span><h2>Yeni bir başlangıç için</h2></div>
    @if($listings->total())<span>{{ $listings->total() }} ilan</span>@endif
  </div>
  <div class="adopt-grid">
    @forelse($listings as $listing)
      <a class="cardbox adopt-card" href="{{ route('adoption.show',$listing) }}">
        <div class="adopt-photo">@if($listing->photo_path)<img src="{{ route('adoption.photo',$listing) }}" alt="{{ $listing->pet_name }} için sahiplendirme fotoğrafı" loading="lazy">@else<span aria-hidden="true">{{ $listing->species==='cat'?'🐱':($listing->species==='dog'?'🐶':'🐾') }}</span>@endif<b>{{ $listing->species==='cat'?'Kedi':($listing->species==='dog'?'Köpek':'Diğer dost') }}</b></div>
        <div class="adopt-info"><span class="chip">{{ $listing->breed ?: 'Irkı belirtilmemiş' }}</span><h3>{{ $listing->pet_name }}</h3><p>{{ $listing->age_label }} · {{ $listing->sex_label ?? 'Bilgi alın' }}</p><p>📍 {{ $listing->district ? $listing->district.', ' : '' }}{{ $listing->city }}</p><span class="adopt-link">Tanışma bilgileri <b aria-hidden="true">→</b></span></div>
      </a>
    @empty
      <section class="adopt-empty">
        <div class="adopt-empty-art" aria-hidden="true"><span>🐾</span><i></i><i></i></div>
        <div class="adopt-empty-copy"><span class="forum-card-label">{{ request()->filled('q')||request()->filled('city')||request()->filled('species') ? 'ARAMA SONUCU' : 'İLK İLANLAR YOLDA' }}</span>
          <h2>{{ request()->filled('q')||request()->filled('city')||request()->filled('species') ? 'Bu ölçütlerde ilan bulamadık.' : 'Bu sayfa yeni yuvasını bekliyor.' }}</h2>
          <p>{{ request()->filled('q')||request()->filled('city')||request()->filled('species') ? 'Aramanızı genişletebilir veya tüm ilanlara dönebilirsiniz.' : 'Henüz onaylanmış ilan yok. Bir dosta yuva arıyorsanız güvenli ve sorumlu sahiplendirme sürecini başlatabilirsiniz.' }}</p>
          <div class="adopt-hero-actions">
            @if(request()->filled('q')||request()->filled('city')||request()->filled('species'))<a class="btn ghost" href="{{ route('adoption.index') }}">Filtreleri temizle</a>@endif
            <a class="btn" href="{{ auth()->check()?route('adoption.create'):route('login') }}">İlan oluştur <span aria-hidden="true">→</span></a>
          </div>
        </div>
      </section>
    @endforelse
  </div>
  {{ $listings->links() }}

  <section class="adopt-safety"><div><span class="eyebrow">SORUMLU SAHİPLENDİRME</span><h2>Tanışın, sorun, kararınızı güvenle verin.</h2></div><p>Sahiplendirme karşılıklı güven ve sorumluluk gerektirir. Peti teslim almadan önce sahibiyle tanışın; sağlık bilgilerini ve aşı kayıtlarını doğrulayın. Acil veya şüpheli bir durumda ilanı ekibimize bildirin.</p></section>
</div>
@endsection