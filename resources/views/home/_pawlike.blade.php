{{-- Ilk kez rehber bandi --}}
<section class="pw-bolum"><div class="wrap"><div class="pw-rehber">
  <div>
    <span class="pw-etiket">REHBER</span>
    <h2>İlk kez pet otel mi kullanacaksınız?</h2>
    <p>Dostunuzu ilk kez bir otelde bırakırken nelere dikkat etmeniz, hangi belgeleri hazırlamanız gerektiğini adım adım anlatan rehberimizi okuyun.</p>
    <a class="btn" href="{{ route('pages.show', 'pet-otel-nedir') }}">Rehberi oku</a>
  </div>
  <div class="pw-rehber-img" aria-hidden="true">
    <span>&#128054;</span><span>&#128049;</span><span>&#129460;</span>
  </div>
</div></div></section>

{{-- Seffaflik: yalnizca sayfadaki gercek verilerden --}}
@php $lisansli = $hoteller->whereNotNull('license_verified_at')->count(); @endphp
<section class="pw-bolum"><div class="wrap">
  <h2>Karar vermeden önce net bilgi</h2>
  <p class="hint">Her otel sayfasında konaklama kuralları ve fiyatlar açıkça yazılır.</p>
  <div class="pw-seffaf">
    <div><b>Açık fiyat ve oda tipi</b><span>Günlük ücret, oda boyutu ve kabul edilen hayvan türü otel sayfasında görünür.</span></div>
    <div><b>Giriş-çıkış saatleri</b><span>Teslim ve teslim alma saatlerini rezervasyondan önce görürsünüz.</span></div>
    <div><b>{{ $onlineBookingEnabled ? 'Çevrimiçi rezervasyon' : 'İşletmeyle doğrudan iletişim' }}</b><span>{{ $onlineBookingEnabled ? 'Talep platforma kaydedilir; e-posta iletimi doğrulanamazsa uyarı gösterilir. Rezervasyon işletme onayıyla kesinleşir.' : 'Çevrimiçi rezervasyon talepleri şu anda alınmıyor. Müsaitlik ve koşulları işletmeyle teyit edin.' }}</span></div>
    @if($lisansli > 0)<div><b>Doğrulanmış lisans</b><span>{{ $lisansli }} tesisin lisans bilgisi doğrulanmıştır.</span></div>@endif
  </div>
</div></section>
