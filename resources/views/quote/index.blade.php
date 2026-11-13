@extends('layouts.app')
@section('title', 'Birden fazla pet otelden fiyat al | Antalya Pet Otel')
@section('desc', 'Tarihlerinizi ve evcil hayvan bilginizi bir kez yazın, seçtiğiniz pet otellere hazır WhatsApp mesajı oluşturun. Fiyat ve müsaitlik doğrudan işletmeden öğrenilir.')
@section('canonical', route('quote.index'))
@push('head')
<link rel="stylesheet" href="{{ asset('css/fiyat-al.css') }}?v={{ @filemtime(public_path('css/fiyat-al.css')) }}">
@endpush
@section('content')
<div class="wrap fiyat-al">
  <nav class="fiyat-al__crumb" aria-label="Sayfa yolu"><a href="{{ url('/') }}">Ana Sayfa</a><span aria-hidden="true">/</span><span aria-current="page">Birden fazla otelden fiyat al</span></nav>
  <h1>Birden fazla pet otelden fiyat al</h1>
  <p class="fiyat-al__lead">Tarihlerinizi ve evcil hayvanınızın bilgisini bir kez yazın, seçtiğiniz tesislere hazır bir WhatsApp mesajı oluşturalım. Fiyat ve müsaitliği doğrudan işletmeden öğrenirsiniz.</p>

  <form id="fiyatForm" class="fiyat-al__form" novalidate>
    <div class="fiyat-al__grid">
      <label>Evcil hayvan
        <select name="tur"><option value="kopek" @selected($tur !== 'kedi')>Köpek</option><option value="kedi" @selected($tur === 'kedi')>Kedi</option></select>
      </label>
      <label>Adet
        <input type="number" name="adet" min="1" max="5" value="1" inputmode="numeric">
      </label>
      <label>Giriş tarihi
        <input type="date" name="giris">
      </label>
      <label>Çıkış tarihi
        <input type="date" name="cikis">
      </label>
      <label data-only="kopek">Köpeğin boyutu <small>(isteğe bağlı)</small>
        <select name="boyut"><option value="">Belirtmeyeyim</option><option>Küçük ırk</option><option>Orta ırk</option><option>Büyük ırk</option></select>
      </label>
      <label>İlçe
        <select name="ilce"><option value="">Tüm ilçeler</option>@foreach($ilceler as $i)<option>{{ $i }}</option>@endforeach</select>
      </label>
    </div>
    <label class="fiyat-al__not">Not <small>(isteğe bağlı, en fazla 140 karakter)</small>
      <input type="text" name="not" maxlength="140" placeholder="Örn. aşıları tam, ilaç kullanıyor">
    </label>

    <fieldset class="fiyat-al__oteller">
      <legend>Tesisleri seçin <small>(en fazla 5)</small></legend>
      <ul id="fiyatOteller">
        @forelse($oteller as $h)
        <li data-ilce="{{ $h['ilce'] }}" data-kopek="{{ $h['kopek'] ? 1 : 0 }}" data-kedi="{{ $h['kedi'] ? 1 : 0 }}">
          <label><input type="checkbox" name="otel" value="{{ $h['slug'] }}" data-name="{{ $h['name'] }}" data-wa="{{ $h['wa'] }}"><span><b>{{ $h['name'] }}</b><small>{{ $h['ilce'] }}</small></span></label>
          <a href="{{ route('hotels.show', $h['slug']) }}" target="_blank" rel="noopener">İncele</a>
        </li>
        @empty
        <li class="fiyat-al__bos">Şu an WhatsApp ile ulaşılabilen tesis bulunamadı.</li>
        @endforelse
      </ul>
    </fieldset>

    <p id="fiyatHata" class="fiyat-al__hata" role="alert" hidden></p>
    <button type="submit" class="fiyat-al__btn">Mesajları hazırla</button>
  </form>

  <section id="fiyatSonuc" class="fiyat-al__sonuc" aria-live="polite" hidden>
    <h2>Mesajınız hazır</h2>
    <p class="fiyat-al__onizleme" id="fiyatOnizleme"></p>
    <ul id="fiyatLinkler"></ul>
    <p class="fiyat-al__not-kucuk">Mesaj WhatsApp'ta kendi hesabınızdan gönderilir. Bu sayfa girdiğiniz bilgileri saklamaz. Fiyat ve müsaitlik tesisin yanıtına göre değişebilir.</p>
  </section>
</div>
<script src="{{ asset('js/fiyat-al.js') }}?v={{ @filemtime(public_path('js/fiyat-al.js')) }}" defer></script>
@endsection
