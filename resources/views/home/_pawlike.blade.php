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

{{-- One cikan kopek / kedi otelleri (tesis sayisi azken ana listeyi tekrar etmesin) --}}
@if($hoteller->count() >= 8)
@foreach([['kopek', 'Köpek Otelleri', 'kopek-otelleri'], ['kedi', 'Kedi Otelleri', 'kedi-otelleri']] as [$tipKey, $tipAd, $tipSlug])
  @php
    $adayListe = $hoteller->filter(fn($h) => in_array($h->turEtiketi(), [$tipKey, 'ikisi'], true));
    $liste = $adayListe->sortByDesc(fn($h) => (int) ($aylikOtelHit[$h->slug] ?? 0))->take(6);
    $aylikHitToplam = (int) $adayListe->sum(fn($h) => (int) ($aylikOtelHit[$h->slug] ?? 0));
    $aylar = [1 => 'Ocak', 2 => 'Şubat', 3 => 'Mart', 4 => 'Nisan', 5 => 'Mayıs', 6 => 'Haziran', 7 => 'Temmuz', 8 => 'Ağustos', 9 => 'Eylül', 10 => 'Ekim', 11 => 'Kasım', 12 => 'Aralık'];
    $ayAdi = $aylar[(int) now()->format('n')];
  @endphp
  @if($liste->isNotEmpty())
  <section class="pw-bolum"><div class="wrap">
    <div class="pw-satir-bas">
      <div><h2>{{ $ayAdi }} ayında en çok ziyaret edilen {{ mb_strtolower($tipAd, 'UTF-8') }}</h2><p class="hint">Otel sıralaması bu ayki sayfa görüntülenmelerine göre otomatik güncellenir.@if($aylikHitToplam === 0) Bu ay henüz ziyaret verisi oluşmadı.@endif</p></div>
      <a href="{{ ($tipKey === 'kedi' ? route('hotels.cats') : route('hotels.dogs')) }}">Tümünü gör &rarr;</a>
    </div>
    <div class="grid">@foreach($liste as $h)@include('hotels._card', ['h' => $h])@endforeach</div>
  </div></section>
  @endif
@endforeach
@endif

{{-- Seffaflik: yalnizca sayfadaki gercek verilerden --}}
@php $lisansli = $hoteller->whereNotNull('license_verified_at')->count(); @endphp
<section class="pw-bolum"><div class="wrap">
  <h2>Karar vermeden önce net bilgi</h2>
  <p class="hint">Her otel sayfasında konaklama kuralları ve fiyatlar açıkça yazılır.</p>
  <div class="pw-seffaf">
    <div><b>Açık fiyat ve oda tipi</b><span>Günlük ücret, oda boyutu ve kabul edilen hayvan türü otel sayfasında görünür.</span></div>
    <div><b>Giriş-çıkış saatleri</b><span>Teslim ve teslim alma saatlerini rezervasyondan önce görürsünüz.</span></div>
    <div><b>Doğrudan otele talep</b><span>Rezervasyon talebiniz ilgili tesise iletilir; ödeme otelde yapılır.</span></div>
    @if($lisansli > 0)<div><b>Doğrulanmış lisans</b><span>{{ $lisansli }} tesisin lisans bilgisi doğrulanmıştır.</span></div>@endif
  </div>
</div></section>
