@extends('layouts.app')
@php
  $siteBrand = \App\Models\Setting::al('site_name', config('app.name', 'Pet Otel'));
  $siteCity = \App\Models\Setting::al('site_city', '');
  $contactDescription = 'Tesis kaydı, ilan düzeltme, gizlilik ve rezervasyon konularında ' . $siteBrand . ' ekibine ulaşın.';
@endphp
@section('title', 'İletişim | ' . $siteBrand)
@section('desc', $contactDescription)
@push('head')
{!! sema_ld(sema_bolum('ContactPage', [
    'name' => 'İletişim',
    'url' => url()->current(),
    'inLanguage' => 'tr-TR',
    'description' => $contactDescription,
])) !!}
{!! sema_kirinti([
    ['ad' => 'Ana Sayfa', 'url' => route('home')],
    ['ad' => 'İletişim'],
]) !!}
@endpush
@section('content')
<div class="wrap contact-help-page">
<header class="contact-help-hero"><div class="crumb"><a href="{{ route('home') }}">Ana Sayfa</a><span aria-hidden="true">/</span><span>İletişim</span></div><span class="static-content-kicker">{{ $siteBrand }} · DESTEK</span><h1>Size yardımcı olmak için buradayız</h1><p>{{ $siteCity ? $siteCity . ' bölgesindeki tesis kaydı, rezervasyon ve kişisel veri talepleriniz için ' : '' }}iletişim formunu kullanabilirsiniz.</p></header>

<div class="contact-help-grid"><article class="prose contact-help-copy">
<h2>Bize ne yazabilirsiniz?</h2>
<p>Formu üç amaçla kullanabilirsiniz. Birincisi, bir tesise ait ilan bilgisinde hata fark
ettiyseniz bildirmek: oda ücreti, giriş-çıkış saati veya kabul edilen hayvan türü yanlış
görünüyorsa düzeltilmesini isteyin. İkincisi, rezervasyon talebinize dönüş almadıysanız
durumu yazmak. Üçüncüsü, kişisel verilerinizin silinmesi veya işlenme amacıyla ilgili
KVKK talepleri.</p>

<h2>Tesisiniz varsa</h2>
<p>Pet otel, kedi oteli veya köpek oteli işletiyorsanız önce <a href="{{ route('partner.create') }}">işletme hesabı oluşturun</a>. Paneli demo olarak inceledikten sonra ücretli aboneliğinizi etkinleştirin; tesis ekleme ve gerçek oda, rezervasyon işlemleri abonelik doğrulanınca açılır. Yeni tesis bilgileri yayın öncesi ekibimiz tarafından incelenir.</p>

<h2>Yanıt süresi</h2>
<p>Mesajlar hafta içi okunur. Aynı tesise ait birden fazla mesaj göndermeyin; önceki
mesajınızı hatırlatarak tek bir talepte bulunmanız işlenmesini hızlandırır. Rezervasyon
konusunda aciliyet varsa yalnızca formu kullanmayın, doğrudan tesisi arayın.</p>

<h2>Rezervasyon talebi hakkında</h2>
<p>{{ \App\Models\Setting::al('booking_requests_enabled', '0') === '1'
    ? 'Çevrimiçi talep platforma kaydedilir; e-posta iletimi doğrulanamazsa uyarı gösterilir. Bu durumda veya işletmeden dönüş alamazsanız, telefon ya da WhatsApp üzerinden işletmeyle teyit edin. Rezervasyon işletme onayı olmadan kesinleşmez.'
    : 'Çevrimiçi rezervasyon talepleri şu anda alınmıyor ve işletmelere iletilmiyor. Konaklama planı için ilgili işletmeyle doğrudan telefon veya WhatsApp üzerinden iletişime geçin.' }}</p>
</article>

<form class="form contact-help-form" method="post" action="{{ route('contact.send') }}">@csrf
  <div class="hp"><label>Web sitesi</label><input type="text" name="website" tabindex="-1" autocomplete="off"></div>
  <label>Ad Soyad</label>
  <input type="text" name="name" value="{{ old('name', auth()->user()?->name) }}" required>
  <div class="row2">
    <div><label>E-posta</label><input type="email" name="email" value="{{ old('email', auth()->user()?->email) }}" required></div>
    <div><label>Telefon</label><input type="tel" name="phone" value="{{ old('phone') }}"></div>
  </div>
  <label>Mesajınız</label>
  <textarea name="message" required placeholder="Hangi konuda yardıma ihtiyacınız var? Tesis adını yazarsanız ilgili kayda bakabiliriz.">{{ old('message') }}</textarea>
  @include('partials.captcha',['captchaKey'=>'contact'])
  <button class="btn">Gönder</button>
</form>
</div>
</div>
@endsection
