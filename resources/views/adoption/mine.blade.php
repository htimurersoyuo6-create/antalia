@extends('layouts.app')
@section('title','Sahiplendirme ilanlarım')
@section('content')
<div class="wrap page adoption-mine">
  <section class="adopt-section-heading"><div><span class="eyebrow">İLAN YÖNETİMİ</span><h1>İlanlarım</h1><p>İlan durumunu takip edin; bilgilerde değişiklik olursa güncelleyip yeniden incelemeye gönderin.</p></div><a class="btn" href="{{ route('adoption.create') }}">Yeni ilan ekle <span aria-hidden="true">→</span></a></section>
  <div class="adopt-mine-list">
    @forelse($listings as $listing)
      <article class="cardbox adopt-mine-card">
        <div class="adopt-mine-photo">@if($listing->photo_path)<img src="{{ route('adoption.photo',$listing) }}" alt="{{ $listing->pet_name }}">@else{{ $listing->species==='cat'?'🐱':($listing->species==='dog'?'🐶':'🐾') }}@endif</div>
        <div class="adopt-mine-content"><h2>{{ $listing->pet_name }}</h2><p>{{ $listing->city }}{{ $listing->district ? ' · '.$listing->district : '' }} · {{ $listing->age_label }}</p><span class="chip">{{ $listing->status==='pending'?'İnceleme bekliyor':($listing->status==='active'?'Yayında':($listing->status==='adopted'?'Sahiplendirildi':($listing->status==='rejected'?'Düzenleme gerekli':'Yayında değil'))) }}</span>@if($listing->status==='rejected')<p>İlanı düzenleyip yeniden incelemeye gönderebilirsiniz.</p>@endif</div>
        <div class="adopt-mine-actions">@if($listing->status==='active')<a class="btn ghost" href="{{ route('adoption.show',$listing) }}">İlanı gör</a><form method="post" action="{{ route('adoption.close',$listing) }}">@csrf<button class="btn ghost" type="submit">Sahiplendirildi</button></form>@endif @if($listing->status!=='adopted')<a class="btn ghost" href="{{ route('adoption.edit',$listing) }}">Düzenle</a>@endif</div>
      </article>
    @empty
      <div class="cardbox adopt-empty"><div class="adopt-empty-art" aria-hidden="true"><span>🐾</span><i></i><i></i></div><div class="adopt-empty-copy"><span class="eyebrow">İLK İLANINIZ</span><h2>Bir dosta yeni yuva bulmaya hazır mısınız?</h2><p>İlanınızı oluşturun. Bilgiler incelendikten sonra sahiplendirme sayfasında yayınlanır.</p><a class="btn" href="{{ route('adoption.create') }}">İlan oluştur →</a></div></div>
    @endforelse
  </div>
  {{ $listings->links() }}
</div>
@endsection
