@extends('layouts.app')
@section('title', 'Ücretsiz pet oteli listeleme başvurusu | Antalya Pet Otel')
@section('desc', 'Pet otelinizi Antalya Pet Otel’de ücretsiz tanıtmak için tesis başvurusu gönderin.')
@section('content')
<main class="wrap partner-listing-page">
  <nav class="partner-breadcrumb" aria-label="Sayfa yolu"><a href="{{ route('home') }}">Ana sayfa</a><span>/</span><a href="{{ route('partner.create') }}">İşletmeler</a><span>/</span><span>Ücretsiz tesis başvurusu</span></nav>
  <section class="partner-listing-card">
    <span class="partner-eyebrow"><i></i> İŞLETMELER İÇİN · ÜCRETSİZ BAŞVURU</span>
    <h1>Tesisinizi misafirlerle buluşturun</h1>
    <p class="partner-listing-intro">Tesis bilgilerinizi gönderin. Ekibimiz işletme bilgilerini doğrulayıp listeleme süreci için sizinle iletişime geçsin. Başvuru sırasında ödeme veya abonelik taahhüdü alınmaz.</p>
    @if(session('ok'))<div class="partner-listing-success" role="status"><b>Başvurunuz alındı</b><span>{{ session('ok') }}</span></div>@endif
    @if($errors->any())<div class="partner-errors" role="alert"><b>Bilgileri kontrol edin</b><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    @auth
      @if(auth()->user()->isOwner())<a class="partner-listing-status-link" href="{{ route('partner.listing.status') }}">Gönderdiğim başvuruların durumunu görüntüle →</a>@endif
    @endauth
    <form method="post" action="{{ route('partner.listing.store') }}" class="partner-listing-form">
      @csrf
      <div class="partner-listing-grid">
        <label>Tesis adı<input name="hotel_name" value="{{ old('hotel_name') }}" maxlength="160" required placeholder="Örn. Lara Pet Otel"></label>
        <label>İl<select name="city" required><option value="">İl seçin</option>@foreach($cities as $city)<option value="{{ $city->name }}" @selected(old('city')===$city->name)>{{ $city->name }}</option>@endforeach</select></label>
        <label>İlçe<input name="district" value="{{ old('district') }}" maxlength="100" placeholder="Örn. Muratpaşa"></label>
        <label>İşletme telefonu<input type="tel" name="phone" value="{{ old('phone') }}" maxlength="30" autocomplete="tel" required placeholder="05XX XXX XX XX"></label>
        <label>Yetkili ad soyad<input name="name" value="{{ old('name') }}" maxlength="120" autocomplete="name" required></label>
        <label>E-posta<input type="email" name="email" value="{{ old('email') }}" maxlength="160" autocomplete="email" required></label>
        <label class="partner-listing-wide">Tesis web sitesi <small>İsteğe bağlı</small><input type="url" name="website" value="{{ old('website') }}" maxlength="200" placeholder="https://"></label>
      </div>
      <fieldset class="partner-listing-types"><legend>Hangi dostları kabul ediyorsunuz?</legend><label><input type="checkbox" name="pet_types[]" value="dog" @checked(in_array('dog',(array)old('pet_types',[]),true))> Köpek</label><label><input type="checkbox" name="pet_types[]" value="cat" @checked(in_array('cat',(array)old('pet_types',[]),true))> Kedi</label></fieldset>
      <label class="partner-listing-wide">Ek bilgi <small>İsteğe bağlı</small><textarea name="note" maxlength="2000" rows="4" placeholder="Tesisiniz, oda seçenekleri veya bize ulaşabileceğimiz uygun zaman hakkında bilgi verin.">{{ old('note') }}</textarea></label>
      <label class="partner-kvkk"><input type="checkbox" name="kvkk" value="1" required><span>Başvuru bilgilerimin listeleme talebimi değerlendirmek ve benimle iletişim kurmak için kullanılmasını kabul ediyorum. <a href="{{ route('pages.show','gizlilik') }}" target="_blank" rel="noopener">Gizlilik / KVKK</a></span></label>
      <div class="partner-listing-trap" aria-hidden="true"><label>Web sitesi<input name="company_website" tabindex="-1" autocomplete="off"></label></div>
      @include('partials.captcha',['captchaKey'=>'partner-listing'])
      <button class="partner-submit" type="submit">Ücretsiz başvuruyu gönder <span aria-hidden="true">→</span></button>
      <p class="partner-listing-footnote">Bu form tesis tanıtım başvurusudur; rezervasyon veya ücretli abonelik oluşturmaz. Bilgiler doğrulandıktan sonra yayın ve işletme paneli seçenekleri size açıklanır.</p>
    </form>
  </section>
</main>
@endsection
