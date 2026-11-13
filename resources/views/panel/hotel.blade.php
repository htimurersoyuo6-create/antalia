@extends('layouts.app')
@section('title', $hotel->name.' – Panel')
@section('content')
<div class="wrap page hotel-editor-page"><div class="crumb hotel-editor-crumb"><a href="{{ route('panel.index') }}">Panel</a><span aria-hidden="true">›</span><span>Tesis yönetimi</span><span aria-hidden="true">›</span><span>{{ $hotel->name }}</span></div>
<header class="hotel-editor-hero"><div class="hotel-editor-hero-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M3 21h18M5 21V7l7-4 7 4v14M9 9h.01M15 9h.01M9 13h.01M15 13h.01M10 21v-4h4v4"/></svg></div><div class="hotel-editor-hero-copy"><span class="hotel-editor-eyebrow">TESİS YÖNETİMİ</span><h1>{{ $hotel->name }}</h1><p>Tesis profilinizi, odalarınızı ve misafirlerinize sunduğunuz hizmetleri tek yerden yönetin.</p></div><div class="hotel-editor-status {{ $hotel->status === 'active' ? 'is-live' : 'is-pending' }}"><i aria-hidden="true"></i>{{ $hotel->status === 'active' ? 'Yayında' : 'İnceleme bekliyor' }}</div></header>
@if(!$hotel->parent_hotel_id)<div style="margin:12px 0 18px"><a class="btn ghost" href="{{ route('panel.branches.index', $hotel) }}">Şube ve lokasyonları yönet</a></div>@endif
@php
  $hotelSections = [
    'profile' => ['Tesis profili', 'İşletme bilgileri, iletişim ve hizmet ayarları', '⌂'],
    'widget' => ['Rezervasyon kutusu', 'Web sitenizde doğrudan rezervasyon alın', '▣'],
    'rooms' => ['Odalar ve doluluk', 'Oda rack, fiyatlar ve giriş kayıtları', '▦'],
    'extras' => ['Ek hizmetler', 'Bakım, ürün ve hizmet kataloğu', '✦'],
    'campaigns' => ['Kampanyalar', 'Uzun konaklama indirimleri', '％'],
  ];
@endphp
<nav class="hotel-editor-nav" aria-label="Tesis yönetimi sayfaları">@foreach($hotelSections as $key => [$label, $description, $icon])<a href="{{ $key === 'profile' ? route('panel.hotel.edit', $hotel) : route('panel.hotel.section', [$hotel, $key]) }}" class="{{ $section === $key ? 'active' : '' }}" @if($section === $key) aria-current="page" @endif><span aria-hidden="true">{{ $icon }}</span><strong>{{ $label }}</strong><small>{{ $description }}</small><b aria-hidden="true">→</b></a>@endforeach</nav>
@if($hotel->status==='pending')<div class="flash ok" style="margin-bottom:16px">Başvurunuz inceleniyor. Odalarınızı ve bilgilerinizi şimdiden tamamlayın.</div>@endif
@if($section === 'profile')
<form id="hotel-profile" class="form wide hotel-editor-form" method="post" enctype="multipart/form-data" action="{{ route('panel.hotel.update', $hotel) }}">@csrf @method('PUT')
<section class="hotel-editor-section"><div class="hotel-editor-section-head"><span class="hotel-editor-section-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M4 21V5a2 2 0 0 1 2-2h9l5 5v13H4Zm10-18v6h6M8 13h8M8 17h8"/></svg></span><div><h2>Tesis profili</h2><p>Misafirlerin tesisinizi tanıması ve sizinle iletişime geçmesi için bilgileri tamamlayın.</p></div><span class="hotel-editor-step">01</span></div>
<div class="hotel-editor-subsection"><div class="hotel-editor-subhead"><span class="hotel-editor-mini-icon" aria-hidden="true">🛍</span><div><h3>Mağaza ve teslimat</h3><p>Ürün satışını ve misafirlere teslimat koşullarını yönetin.</p></div></div><p class="hint">Oteliniz herkese ürün satabilir. Ürün ve sipariş yönetimi ayrı Mağaza menüsündedir.</p><label class="chk"><input type="checkbox" name="shop_enabled" value="1" @checked(old('shop_enabled',$hotel->shop_enabled))> Otel mağazasını açık tut</label><label>Teslimat seçenekleri</label><select name="shop_fulfillment"><option value="pickup" @selected(old('shop_fulfillment',$hotel->shop_fulfillment)==='pickup')>Otelden teslim al</option><option value="delivery" @selected(old('shop_fulfillment',$hotel->shop_fulfillment)==='delivery')>Adrese teslim</option><option value="both" @selected(old('shop_fulfillment',$hotel->shop_fulfillment)==='both')>Her ikisi</option></select><div class="row2"><div><label>Adrese teslimat ücreti (₺)</label><input type="number" min="0" step="0.01" name="shop_delivery_fee" value="{{ old('shop_delivery_fee',$hotel->shop_delivery_fee??0) }}"></div><div><label>Ücretsiz teslimat alt limiti (₺)</label><input type="number" min="0" step="0.01" name="shop_free_delivery_min" value="{{ old('shop_free_delivery_min',$hotel->shop_free_delivery_min) }}"><p class="hint">Boş bırakırsanız ücretsiz teslimat uygulanmaz.</p></div></div><a class="btn" href="{{ route('panel.shop.products',$hotel) }}">Ürün ve sipariş yönetimi</a></div>
<div class="row2"><div><label>Otel adı</label><input type="text" name="name" value="{{ old('name',$hotel->name) }}" required></div>
<div><label>Şehir</label><select name="city_id" id="hotel-city-edit" required>@foreach($cities as $city)<option value="{{ $city->id }}" @selected(old('city_id',$hotel->city_id)==$city->id)>{{ $city->name }}</option>@endforeach</select></div><div><label>İlçe</label><select name="district_id" id="hotel-district-edit" required>@foreach($cities as $city)@foreach($city->districts as $d)<option value="{{ $d->id }}" data-city="{{ $city->id }}" @selected(old('district_id',$hotel->district_id)==$d->id)>{{ $d->name }}</option>@endforeach @endforeach</select></div></div>
<label>Açıklama</label><textarea name="description">{{ old('description',$hotel->description) }}</textarea>
<label>Otel kuralları (aşı, giriş-çıkış saati, iptal vb.)</label><textarea name="policies">{{ old('policies',$hotel->policies) }}</textarea>
<div class="row2"><div><label>Adres</label><input type="text" name="address" value="{{ old('address',$hotel->address) }}"></div><div><label>Telefon</label><input type="tel" name="phone" value="{{ old('phone',$hotel->phone) }}"></div></div>
<div class="row2"><div><label>İşletme / ruhsat no</label><input type="text" name="license_no" value="{{ old('license_no',$hotel->license_no) }}"><p class="hint">Değiştirirseniz doğrulama yeniden yapılır.</p></div><div><label>Kapak fotoğrafı</label><input type="file" name="cover" accept="image/*"></div></div>
<div class="pw-galeri-panel">
  <label>Galeri fotoğrafları <span class="hint">(en fazla 12; JPG, PNG veya WebP, her biri en çok 5 MB)</span></label>
  @php $mevcut = json_decode((string) ($hotel->photos ?? ''), true); $mevcut = is_array($mevcut) ? $mevcut : []; @endphp
  @if($mevcut)
    <div class="pw-galeri-liste">
      @foreach($mevcut as $yol)
        <label class="pw-galeri-oge">
          <img src="{{ asset('storage/'.ltrim($yol, '/')) }}" alt="" loading="lazy" width="140" height="105">
          <span><input type="checkbox" name="photos_remove[]" value="{{ $yol }}"> Sil</span>
        </label>
      @endforeach
    </div>
  @endif
  <input type="file" name="photos_new[]" accept="image/jpeg,image/png,image/webp" multiple>
  <p class="hint">Kapak fotoğrafı listelerde, diğerleri otel sayfasındaki mozaikte görünür. Yüzü görünen kişi, tabela veya telefon numarası içeren kareleri kullanmayın.</p>
</div>
<div class="row2"><div><label>Enlem</label><input type="text" name="lat" value="{{ old('lat',$hotel->lat) }}"></div><div><label>Boylam</label><input type="text" name="lng" value="{{ old('lng',$hotel->lng) }}"></div></div>
@php
  $amHam = trim((string) ($hotel->amenities ?? ''));
  $amJ = json_decode($amHam, true);
  $amListe = is_array($amJ) ? array_map('strval', $amJ) : ($amHam !== '' ? array_map('trim', explode(',', $amHam)) : []);
  $amAlt = array_map('mb_strtolower', $amListe);
  $katAlt = array_map('mb_strtolower', \App\Models\Hotel::STANDART_OLANAKLAR);
  $amDiger = array_values(array_filter($amListe, fn ($x) => $x !== '' && !in_array(mb_strtolower($x), $katAlt, true)));
@endphp
<div class="hotel-editor-subsection"><div class="hotel-editor-subhead"><span class="hotel-editor-mini-icon" aria-hidden="true">📷</span><div><h3>Canlı kamera erişimi</h3><p>Onaylı misafirler için güvenli kamera bağlantısını yönetin.</p></div></div>
<p class="hint">Onaylı misafir, yalnızca konaklama tarihleri boyunca bu HTTPS yayın bağlantısını görebilir. RTSP adresleri tarayıcıda açılamaz.</p>
<label class="chk"><input type="checkbox" name="camera_enabled" value="1" @checked(old('camera_enabled',$hotel->camera_enabled))> Konaklama sırasında canlı kamera erişimini etkinleştir</label>
<label>Güvenli yayın / gömülü oynatıcı HTTPS bağlantısı</label>
<input type="url" name="camera_embed_url" value="{{ old('camera_embed_url') }}" placeholder="https://kamera-saglayici.example/izle/..." autocomplete="off">
<p class="hint">Bağlantı veritabanında şifreli saklanır; form açılırken tekrar gösterilmez.</p>
</div><div class="hotel-editor-subsection"><div class="hotel-editor-subhead"><span class="hotel-editor-mini-icon" aria-hidden="true">🚐</span><div><h3>Pet transfer</h3><p>Transfer hizmetinizi ve ücretlendirme koşullarınızı tanımlayın.</p></div></div>
<p class="hint">Transfer fiyatı ücretsiz OSRM rotasıyla hesaplanır (150 ₺ başlangıç + 15 ₺/km, round trip iki yol mesafesi). Rota bulunamazsa bu tesis için sabit ilçe/bölge tarifesi tanımlayabilirsiniz.</p>
<label class="chk"><input type="checkbox" name="transfer_enabled" value="1" @checked(old('transfer_enabled',$hotel->transfer_enabled ?? false))> Pet transfer hizmeti veriyorum</label>
<label>Transfer türü</label>
<select name="transfer_mode"><option value="free" @selected(old('transfer_mode',$hotel->transfer_mode ?? 'free')==='free')>Ücretsiz</option><option value="paid" @selected(old('transfer_mode',$hotel->transfer_mode ?? 'free')==='paid')>Ücretli</option></select>
<a class="btn ghost" href="{{ route('panel.transfer-zones.index',$hotel) }}">Sabit bölge tarifelerini yönet</a>
<label>Hizmet kapsamı ve bilgilendirme</label><input name="transfer_instructions" maxlength="500" value="{{ old('transfer_instructions',$hotel->transfer_instructions ?? '') }}" placeholder="Hizmet bölgesi, saatler, taşıma şartları">
</div><div class="hotel-editor-subsection"><div class="hotel-editor-subhead"><span class="hotel-editor-mini-icon" aria-hidden="true">✦</span><div><h3>Olanaklar</h3><p>Misafirlerin arama ve filtrelerinde gösterilecek özellikleri seçin.</p></div></div>
<p class="hint">Misafirler listelerde bu olanaklara göre filtreleme yapar; doğru işaretleyin.</p>
<div class="olanak-liste">@foreach(\App\Models\Hotel::STANDART_OLANAKLAR as $ol)
  <label class="chk"><input type="checkbox" name="olanak[]" value="{{ $ol }}" @checked(old('olanak') !== null ? in_array($ol, (array) old('olanak'), true) : in_array(mb_strtolower($ol), $amAlt, true))> {{ $ol }}</label>
@endforeach</div>
<label>Diğer olanaklar <span class="hint">(virgülle ayırın)</span></label>
<input type="text" name="olanak_diger" value="{{ old('olanak_diger', implode(', ', $amDiger)) }}" maxlength="900">
<label class="chk"><input type="checkbox" name="accepts_dogs" value="1" @checked(old('accepts_dogs',$hotel->accepts_dogs))> Köpek kabul</label>
<label class="chk"><input type="checkbox" name="accepts_cats" value="1" @checked(old('accepts_cats',$hotel->accepts_cats))> Kedi kabul</label>
<label class="chk"><input type="checkbox" name="has_daycare" value="1" @checked(old('has_daycare',$hotel->has_daycare))> Gündüz bakımı var</label>
<button class="btn hotel-editor-save"><span aria-hidden="true">✓</span> Değişiklikleri kaydet</button></section></form>
@endif
@if($section === 'widget')
<section id="hotel-widget" class="box partner-widget-box hotel-editor-section hotel-editor-widget" aria-labelledby="partner-widget-title">
  <div><span class="partner-widget-eyebrow">DOĞRUDAN REZERVASYON</span><h2 id="partner-widget-title">Web sitenize rezervasyon kutusu ekleyin</h2>
  <p class="hint">Ziyaretçiler tarih seçip tesisinizin oda ve fiyatlarını Antalya Pet Otel sayfasında görebilir. Rezervasyon talebi ve mevcut onay akışı aynı şekilde devam eder.</p></div>
  @if($hotel->status === 'active')
    @php
      $widgetUrl = rtrim(config('app.url'), '/') . route('widgets.hotel', $hotel->slug, false);
      $embedName = htmlspecialchars($hotel->name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
      $embedSrc = htmlspecialchars($widgetUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
      $embedCode = '<iframe src="'.$embedSrc.'" title="'.$embedName.' rezervasyon arama" width="100%" height="270" loading="lazy" style="border:0;border-radius:16px;max-width:480px" referrerpolicy="strict-origin-when-cross-origin"></iframe>';
    @endphp
    <label for="partner-widget-code">Kopyalayıp sitenizin uygun alanına yapıştırın</label>
    <div class="partner-widget-code-row"><textarea id="partner-widget-code" readonly rows="3">{{ $embedCode }}</textarea><button class="btn sm" type="button" data-copy-widget>Kodu kopyala</button></div>
    <iframe class="partner-widget-preview" src="{{ $widgetUrl }}" title="{{ $hotel->name }} rezervasyon kutusu önizleme" loading="lazy"></iframe>
    <script>(function(){var b=document.querySelector('[data-copy-widget]'),t=document.getElementById('partner-widget-code');if(!b||!t)return;b.addEventListener('click',function(){var done=function(){b.textContent='Kopyalandı';setTimeout(function(){b.textContent='Kodu kopyala'},1800)};if(navigator.clipboard&&window.isSecureContext){navigator.clipboard.writeText(t.value).then(done).catch(function(){t.select();document.execCommand('copy');done()})}else{t.select();document.execCommand('copy');done()}})})();</script>
  @else
    <p class="partner-widget-pending">Widget, tesisiniz yayına alındığında etkinleşir.</p>
  @endif
</section>
@endif

@if($section === 'rooms')
<section id="hotel-rooms" class="hotel-editor-section hotel-editor-major"><div class="hotel-editor-section-head"><span class="hotel-editor-section-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M3 20V6m0 10h18v4M3 12h18v4M7 12V8h5a3 3 0 0 1 3 3v1M21 12V8"/></svg></span><div><h2>Oda yönetimi ve doluluk</h2><p>Oda tiplerini, fiyatları ve fiziksel odaların bugünkü durumunu takip edin.</p></div><span class="hotel-editor-step">02</span></div><p class="sub">Fiyatlar gecelik, TL. Tarih çakışmalarına göre bir fiziksel oda otomatik atanır; bekleyen ve onaylı rezervasyonlar çift rezervasyonu önlemek için ilgili tarihlerde kapasiteyi bloke eder.</p>
@php
  $tumFizikselOdalar = $hotel->rooms->flatMap(fn($oda) => $oda->inventoryUnits);
  $odaOzet = ['available'=>0, 'occupied'=>0, 'waiting'=>0, 'future'=>0, 'offline'=>0];
  foreach ($tumFizikselOdalar as $fizikselOda) {
    $aktifKonaklamalar = $fizikselOda->reservations->filter(fn($r) => in_array($r->status, ['pending','confirmed'], true) && $r->check_out->gt(today()));
    $bugunDolu = $aktifKonaklamalar->contains(fn($r) => $r->status === 'confirmed' && $r->check_in->lte(today()));
    $bugunBloke = $aktifKonaklamalar->contains(fn($r) => $r->check_in->lte(today()));
    $onayBekliyor = $aktifKonaklamalar->contains(fn($r) => $r->status === 'pending');
    $gelecekRezervasyon = $aktifKonaklamalar->contains(fn($r) => $r->status === 'confirmed' && $r->check_in->gt(today()));
    if ($bugunDolu) $odaOzet['occupied']++;
    if ($fizikselOda->status !== 'available') $odaOzet['offline']++;
    elseif (!$bugunBloke) $odaOzet['available']++;
    if ($onayBekliyor) $odaOzet['waiting']++;
    if ($gelecekRezervasyon) $odaOzet['future']++;
  }
@endphp
<div class="room-inventory-overview" aria-label="Bugünkü oda doluluk özeti">
  <div class="room-inventory-total"><span>Toplam fiziksel oda</span><strong>{{ $tumFizikselOdalar->count() }}</strong><small>{{ $hotel->rooms->count() }} oda tipi</small></div>
  <div class="room-inventory-metric occupied"><span>Şu an dolu</span><strong>{{ $odaOzet['occupied'] }}</strong><small>Konaklaması süren</small></div>
  <div class="room-inventory-metric available"><span>Bugün boş</span><strong>{{ $odaOzet['available'] }}</strong><small>Satışa açık</small></div>
  <div class="room-inventory-metric waiting"><span>Onay bekliyor</span><strong>{{ $odaOzet['waiting'] }}</strong><small>Oda geçici olarak ayrıldı</small></div>
  <div class="room-inventory-metric future"><span>Gelecek rezervasyon</span><strong>{{ $odaOzet['future'] }}</strong><small>Belirtilen tarihlerde ayrıldı</small></div>
  <div class="room-inventory-metric offline"><span>Satışa kapalı</span><strong>{{ $odaOzet['offline'] }}</strong><small>Temizlik, bakım veya tadilat</small></div>
</div>
@php
  $rackDateInput = (string) request('oda_tarihi', today()->toDateString());
  $rackDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', $rackDateInput) ? \Illuminate\Support\Carbon::parse($rackDateInput)->startOfDay() : today()->startOfDay();
  $rackPrev = $rackDate->copy()->subDay()->toDateString();
  $rackNext = $rackDate->copy()->addDay()->toDateString();
  $rackToday = today()->toDateString();
  $rackCounts = ['available'=>0, 'staying'=>0, 'arrival'=>0, 'departure'=>0, 'pending'=>0, 'offline'=>0];
@endphp
<section class="hotel-room-rack" aria-labelledby="hotel-room-rack-title">
  <div class="hotel-room-rack-head"><div class="hotel-room-rack-title"><span class="hotel-room-rack-icon" aria-hidden="true">▦</span><div><h3 id="hotel-room-rack-title">Oda rack</h3><p>Seçtiğiniz tarihteki oda doluluk ve konaklama durumları</p></div></div>
    <form class="hotel-room-rack-date" method="get" action="{{ route('panel.hotel.edit', $hotel) }}"><label for="oda-tarihi">Görüntülenen tarih</label><div><a href="{{ route('panel.hotel.edit', $hotel) }}?oda_tarihi={{ $rackPrev }}#hotel-rooms" aria-label="Önceki gün">‹</a><input id="oda-tarihi" type="date" name="oda_tarihi" value="{{ $rackDate->toDateString() }}"><button type="submit">Göster</button><a href="{{ route('panel.hotel.edit', $hotel) }}?oda_tarihi={{ $rackNext }}#hotel-rooms" aria-label="Sonraki gün">›</a>@if($rackDate->toDateString() !== $rackToday)<a class="hotel-room-rack-today" href="{{ route('panel.hotel.edit', $hotel) }}?oda_tarihi={{ $rackToday }}#hotel-rooms">Bugün</a>@endif</div></form>
  </div>
  <div class="hotel-room-rack-legend" aria-label="Oda durumları"><span class="available"><i></i>Müsait</span><span class="staying"><i></i>Konaklıyor</span><span class="arrival"><i></i>Giriş yapacak</span><span class="departure"><i></i>Çıkış yapacak</span><span class="pending"><i></i>Onay bekliyor</span><span class="offline"><i></i>Bakım / kapalı</span></div>
  <div class="hotel-room-rack-groups">
  @foreach($hotel->rooms as $rackRoom)
    <div class="hotel-room-rack-group"><div class="hotel-room-rack-group-head"><h4>{{ $rackRoom->name }}</h4><span>{{ $rackRoom->inventoryUnits->count() }} fiziksel oda</span></div>
      @if($rackRoom->inventoryUnits->isEmpty())<p class="hotel-room-rack-empty">Henüz fiziksel oda eklenmemiş.</p>@else<div class="hotel-room-rack-grid">
      @foreach($rackRoom->inventoryUnits as $rackUnit)
        @php
          $rackBookings = $rackUnit->reservations->filter(fn($b) => in_array($b->status, ['pending','confirmed'], true) && $b->check_out->toDateString() >= $rackDate->toDateString())->sortBy('check_in');
          $rackBooking = $rackBookings->first(fn($b) => $b->check_in->toDateString() <= $rackDate->toDateString() && $b->check_out->toDateString() >= $rackDate->toDateString());
          $rackTone = 'available'; $rackLabel = 'Müsait';
          if ($rackBooking && $rackBooking->status === 'pending') { $rackTone = 'pending'; $rackLabel = 'Onay bekliyor'; }
          elseif ($rackBooking && $rackBooking->check_out->toDateString() === $rackDate->toDateString()) { $rackTone = 'departure'; $rackLabel = 'Çıkış yapacak'; }
          elseif ($rackBooking && $rackBooking->check_in->toDateString() === $rackDate->toDateString()) { $rackTone = 'arrival'; $rackLabel = 'Giriş yapacak'; }
          elseif ($rackBooking) { $rackTone = 'staying'; $rackLabel = 'Konaklıyor'; }
          elseif ($rackUnit->status !== 'available') { $rackTone = 'offline'; $rackLabel = $rackUnit->status_label; }
          if (isset($rackCounts[$rackTone])) $rackCounts[$rackTone]++;
        @endphp
        <details class="hotel-room-rack-tile {{ $rackTone }}" data-rack-unit="{{ $rackUnit->id }}" @if((string) old('walkin_unit') === (string) $rackUnit->id) open @endif><summary class="hotel-room-rack-tile-summary"><div class="hotel-room-rack-tile-top"><span class="hotel-room-rack-room-icon" aria-hidden="true">▣</span><span class="hotel-room-rack-state"><i></i>{{ $rackLabel }}</span></div><strong class="hotel-room-rack-unit-name">{{ $rackUnit->name }}</strong><span class="hotel-room-rack-room-type">{{ $rackRoom->pet_type_label ?? \App\Models\RoomType::PET_TYPES[$rackRoom->pet_type] ?? 'Konaklama' }} · {{ \App\Models\RoomType::SIZES[$rackRoom->size] ?? '' }}</span>
          @if($rackBooking)<div class="hotel-room-rack-guest"><b>{{ $rackBooking->user->name }}</b><span>{{ $rackBooking->check_in->format('d.m') }} – {{ $rackBooking->check_out->format('d.m') }}</span></div>@elseif($rackUnit->status !== 'available' && $rackUnit->note)<div class="hotel-room-rack-guest"><span>{{ $rackUnit->note }}</span></div>@else<div class="hotel-room-rack-free">+ <span>Boş oda · tıklayıp kayıt aç</span></div>@endif
        </summary>
          @if($rackBooking)
            <div class="hotel-room-rack-expanded"><div class="hotel-room-rack-expanded-title"><span aria-hidden="true">♙</span><b>Konaklayan misafir ve pet</b></div><dl><div><dt>Misafir</dt><dd>{{ $rackBooking->user->name }}</dd></div><div><dt>E-posta</dt><dd>{{ $rackBooking->user->email }}</dd></div><div><dt>Telefon</dt><dd>{{ $rackBooking->user->phone ?: 'Kayıtlı değil' }}</dd></div><div><dt>Pet</dt><dd>{{ $rackBooking->pet->name }} · {{ $rackBooking->pet->species_label }} · {{ $rackBooking->pet->size_label }}</dd></div><div><dt>Konaklama</dt><dd>{{ $rackBooking->check_in->format('d.m.Y') }} – {{ $rackBooking->check_out->format('d.m.Y') }} ({{ $rackBooking->nights }} gece)</dd></div><div><dt>Rezervasyon</dt><dd>{{ $rackBooking->code }} · {{ number_format((float)$rackBooking->total_price, 2, ',', '.') }} ₺</dd></div></dl><a class="hotel-room-rack-detail-link" href="{{ route('panel.reservations.show', $rackBooking) }}">Rezervasyon detayını aç <span aria-hidden="true">→</span></a></div>
          @elseif($rackUnit->status === 'available' && !$rackDate->lt(today()))
            <form class="hotel-room-rack-walkin" method="post" action="{{ route('panel.rooms.walk-in.store', [$hotel, $rackRoom, $rackUnit]) }}">@csrf<input type="hidden" name="walkin_unit" value="{{ $rackUnit->id }}"><input type="hidden" name="check_in" value="{{ $rackDate->toDateString() }}"><div class="hotel-room-rack-expanded-title"><span aria-hidden="true">＋</span><b>Yeni misafir giriş kaydı</b></div><p class="hotel-room-rack-form-note">Bu oda için {{ $rackDate->format('d.m.Y') }} girişli, onaylı bir konaklama kaydı oluşturun. Ücret oda fiyatından hesaplanır; ödeme ilk aşamada alınmadı olarak işlenir.</p>
              <label>Misafir adı soyadı<input name="guest_name" maxlength="160" required value="{{ old('guest_name') }}" placeholder="Ad Soyad"></label><div class="hotel-room-rack-form-row"><label>E-posta<input type="email" name="guest_email" maxlength="160" required value="{{ old('guest_email') }}" placeholder="misafir@ornek.com"></label><label>Telefon<input type="tel" name="guest_phone" maxlength="30" value="{{ old('guest_phone') }}" placeholder="05xx xxx xx xx"></label></div>
              <div class="hotel-room-rack-form-row"><label>Pet adı<input name="pet_name" maxlength="80" required value="{{ old('pet_name') }}" placeholder="Pet adı"></label><label>Hayvan türü<select name="pet_species"><option value="dog" @selected($rackRoom->pet_type === 'dog')>Köpek</option><option value="cat" @selected($rackRoom->pet_type === 'cat')>Kedi</option><option value="other" @selected($rackRoom->pet_type === 'other')>Diğer</option></select></label></div>
              <div class="hotel-room-rack-form-row"><label>Boy<select name="pet_size"><option value="small" @selected($rackRoom->size === 'small')>Küçük</option><option value="medium" @selected($rackRoom->size === 'medium' || $rackRoom->size === 'any')>Orta</option><option value="large" @selected($rackRoom->size === 'large')>Büyük</option></select></label><label>Irk (isteğe bağlı)<input name="pet_breed" maxlength="80" value="{{ old('pet_breed') }}" placeholder="Irk bilgisi"></label></div>
              <label>Çıkış tarihi<input type="date" name="check_out" required min="{{ $rackDate->copy()->addDay()->toDateString() }}" value="{{ old('check_out', $rackDate->copy()->addDay()->toDateString()) }}"></label><label>Pet notu (isteğe bağlı)<textarea name="pet_notes" maxlength="1000" rows="2" placeholder="Bakım veya sağlık için önemli notlar">{{ old('pet_notes') }}</textarea></label><button class="btn hotel-room-rack-create">Giriş kaydını oluştur</button>
            </form>
          @elseif($rackDate->lt(today()))<div class="hotel-room-rack-expanded"><p>Geçmiş tarihli bir güne yeni giriş kaydı oluşturulamaz.</p></div>
          @else<div class="hotel-room-rack-expanded"><div class="hotel-room-rack-expanded-title"><span aria-hidden="true">⚙</span><b>{{ $rackUnit->status_label }}</b></div><p>{{ $rackUnit->note ?: 'Bu oda şu anda yeni rezervasyona kapalı.' }}</p><a class="hotel-room-rack-detail-link" href="#hotel-rooms">Oda durumunu yönet</a></div>@endif
        </details>
      @endforeach
      </div>@endif
    </div>
  @endforeach
  </div>
  <div class="hotel-room-rack-foot">{{ $rackDate->locale('tr')->translatedFormat('d F Y, l') }} <span>·</span> <b>{{ $rackCounts['available'] }}</b> müsait <span>·</span> <b>{{ $rackCounts['staying'] }}</b> konaklıyor <span>·</span> <b>{{ $rackCounts['arrival'] + $rackCounts['departure'] }}</b> giriş / çıkış</div>
</section>
@foreach($hotel->rooms as $room)
<div class="cardbox">
<form class="inl" method="post" action="{{ route('panel.rooms.update', [$hotel,$room]) }}">@csrf @method('PUT')
<input name="name" value="{{ $room->name }}" required style="width:170px">
<select name="pet_type">@foreach(\App\Models\RoomType::PET_TYPES as $k=>$v)<option value="{{ $k }}" @selected($room->pet_type===$k)>{{ $v }}</option>@endforeach</select>
<select name="size">@foreach(\App\Models\RoomType::SIZES as $k=>$v)<option value="{{ $k }}" @selected($room->size===$k)>{{ $v }}</option>@endforeach</select>
<input name="capacity" type="number" min="1" value="{{ $room->capacity }}" style="width:70px" title="Kapasite">
<input name="units" type="number" min="1" value="{{ $room->units }}" style="width:70px" title="Oda adedi">
<input name="base_price" type="number" step="1" min="1" value="{{ (int)$room->base_price }}" style="width:100px" title="Gecelik fiyat">
<button class="btn sm">Güncelle</button></form>
<details class="physical-room-list" @if($room->inventoryUnits->count() <= 6) open @endif><summary>Fiziksel odalar ({{ $room->inventoryUnits->count() }}) · temizlik ve bakım durumları</summary>
<div class="physical-room-table-wrap"><table class="physical-room-table"><thead><tr><th>Oda</th><th>Bugünkü durum</th><th>Rezervasyon</th><th>Oda durumu</th></tr></thead><tbody>
@foreach($room->inventoryUnits as $unit)
@php
$blockingBookings = $unit->reservations->filter(fn($b) => in_array($b->status, ['pending','confirmed'], true) && $b->check_out->gt(today()))->sortBy('check_in');
$todayBooking = $blockingBookings->first(fn($b) => $b->check_in->lte(today()));
$nextBooking = $todayBooking ?: $blockingBookings->first();
$unitLabel = $todayBooking ? ($todayBooking->status === 'confirmed' ? 'Konaklıyor' : 'Onay bekliyor') : ($nextBooking ? ($nextBooking->status === 'pending' ? 'Onay bekliyor' : 'Yaklaşan rezervasyon') : $unit->status_label);
$unitTone = $todayBooking ? ($todayBooking->status === 'confirmed' ? 'occupied' : 'waiting') : ($nextBooking ? ($nextBooking->status === 'pending' ? 'waiting' : 'reserved') : ($unit->status === 'available' ? 'available' : 'offline'));
@endphp
<tr class="room-unit-row {{ $unitTone }}"><td><strong>{{ $unit->name }}</strong><small>{{ $room->name }}</small></td><td><span class="room-unit-state {{ $unitTone }}"><i aria-hidden="true"></i>{{ $unitLabel }}</span></td><td>@if($nextBooking)<strong>{{ $nextBooking->user->name }}</strong><small>{{ $nextBooking->check_in->format('d.m.Y') }} – {{ $nextBooking->check_out->format('d.m.Y') }}</small>@else<span class="room-unit-no-booking">Planlı rezervasyon yok</span>@endif</td><td>@if($nextBooking)<span class="room-unit-lock-pill">Rezervasyon nedeniyle durum değişikliği kapalı</span>@else<form method="post" action="{{ route('panel.units.update',[$hotel,$room,$unit]) }}">@csrf @method('PATCH')<select name="status" aria-label="{{ $unit->name }} oda durumu">@foreach(\App\Models\RoomUnit::STATUS as $sk=>$sv)<option value="{{ $sk }}" @selected($unit->status===$sk)>{{ $sv }}</option>@endforeach</select><input name="note" maxlength="300" value="{{ $unit->note }}" placeholder="Temizlik / bakım notu" aria-label="{{ $unit->name }} not"><button class="btn sm ghost">Kaydet</button></form>@endif</td></tr>
@endforeach
</tbody></table></div>
<p class="hint">Dolu ve rezerve odalar tarihe göre otomatik hesaplanır. Temizlik, bakım veya tadilat seçilen fiziksel oda yeni satıştan çıkar.</p></details>
<form method="post" action="{{ route('panel.rooms.destroy', [$hotel,$room]) }}" onsubmit="return confirm('Silinsin mi?')" style="display:inline">@csrf @method('DELETE')<button class="btn sm danger" style="margin-top:8px">Sil</button></form>
<details style="margin-top:10px"><summary>Sezon fiyatları ({{ $room->priceRules->count() }})</summary>
@foreach($room->priceRules as $pr)<div class="inl" style="margin-top:6px">{{ $pr->date_from->format('d.m.Y') }} – {{ $pr->date_to->format('d.m.Y') }}: <b>{{ number_format($pr->price,0,',','.') }} ₺</b>
<form method="post" action="{{ route('panel.rules.destroy', [$hotel,$room,$pr]) }}" style="display:inline">@csrf @method('DELETE')<button class="btn sm ghost">Sil</button></form></div>@endforeach
<form class="inl" style="margin-top:10px" method="post" action="{{ route('panel.rules.store', [$hotel,$room]) }}">@csrf<input type="date" name="date_from" required><input type="date" name="date_to" required><input type="number" name="price" min="1" placeholder="Gecelik ₺" required style="width:110px"><button class="btn sm">Ekle</button></form></details>
</div>
@endforeach
<form class="form wide hotel-editor-subsection hotel-editor-standalone" method="post" action="{{ route('panel.rooms.store', $hotel) }}">@csrf<div class="hotel-editor-subhead"><span class="hotel-editor-mini-icon" aria-hidden="true">＋</span><div><h3>Yeni oda tipi</h3><p>Tesisinize yeni bir konaklama seçeneği ekleyin.</p></div></div>
<div class="row2"><div><label>Ad</label><input type="text" name="name" required></div><div><label>Gecelik fiyat (₺)</label><input type="number" name="base_price" min="1" required></div></div>
<div class="row2"><div><label>Hayvan türü</label><select name="pet_type">@foreach(\App\Models\RoomType::PET_TYPES as $k=>$v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select></div><div><label>Boy</label><select name="size">@foreach(\App\Models\RoomType::SIZES as $k=>$v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select></div></div>
<div class="row2"><div><label>Kapasite (hayvan)</label><input type="number" name="capacity" value="1" min="1" required></div><div><label>Oda adedi</label><input type="number" name="units" value="1" min="1" required></div></div>
<button class="btn">Oda tipi ekle</button></form></section>
@endif

@if($section === 'extras')
<section id="hotel-extras" class="hotel-editor-section hotel-editor-major"><div class="hotel-editor-section-head"><span class="hotel-editor-section-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M12 3v18M3 12h18M5.6 5.6l12.8 12.8m0-12.8L5.6 18.4"/></svg></span><div><h2>Ek hizmet, ürün ve bakım kataloğu</h2><p>Mama, bakım, sağlık ve diğer ek hizmetlerinizi yönetin.</p></div><span class="hotel-editor-step">03</span></div><p class="sub">Müşteriler rezervasyon sırasında veya rezervasyon detayından talep edebilir; fiyat ve teslimat onayı sizdedir.</p>
@foreach($hotel->extras as $ex)
<div class="cardbox"><form class="form wide" method="post" action="{{ route('panel.extras.update', [$hotel, $ex]) }}">@csrf @method('PUT')
<div class="row2"><div><label>Ad</label><input name="name" value="{{ $ex->name }}" maxlength="120" required></div><div><label>Fiyat (₺)</label><input name="price" type="number" min="0.01" step="0.01" value="{{ $ex->price }}" required></div></div>
<label>Kategori</label><select name="category">@foreach(\App\Models\HotelExtra::CATEGORIES as $ck=>$cv)<option value="{{ $ck }}" @selected(($ex->category??'service')===$ck)>{{ $cv }}</option>@endforeach</select>
<label>Açıklama</label><textarea name="description" maxlength="1000" rows="2">{{ $ex->description }}</textarea>
<label>Fiyat birimi</label><select name="unit">@foreach(\App\Models\HotelExtra::UNITS as $k => $v)<option value="{{ $k }}" @selected($ex->unit === $k)>{{ $v }}</option>@endforeach</select>
<label class="chk"><input type="checkbox" name="available_after_booking" value="1" @checked($ex->available_after_booking)> Onaylı rezervasyonlardan sonra da talep edilebilir</label>
<label class="chk"><input type="checkbox" name="active" value="1" @checked($ex->active)> Aktif katalog ürünü</label><button class="btn sm">Güncelle</button></form>
<form method="post" action="{{ route('panel.extras.destroy', [$hotel, $ex]) }}" onsubmit="return confirm('Katalogdan kaldırılsın mı? Geçmiş talepler korunur.')" style="display:inline">@csrf @method('DELETE')<button class="btn sm danger" style="margin-top:8px">Katalogdan kaldır</button></form></div>
@endforeach
<form class="form wide hotel-editor-subsection hotel-editor-standalone" method="post" action="{{ route('panel.extras.store', $hotel) }}">@csrf<div class="hotel-editor-subhead"><span class="hotel-editor-mini-icon" aria-hidden="true">＋</span><div><h3>Yeni ek hizmet veya ürün</h3><p>Kataloğunuza yeni bir hizmet ekleyin.</p></div></div>
<div class="row2"><div><label>Ad</label><input type="text" name="name" maxlength="120" placeholder="Örn: Hipoalerjenik mama" required></div><div><label>Fiyat (₺)</label><input type="number" name="price" min="0.01" step="0.01" required></div></div>
<label>Kategori</label><select name="category">@foreach(\App\Models\HotelExtra::CATEGORIES as $ck=>$cv)<option value="{{ $ck }}">{{ $cv }}</option>@endforeach</select>
<label>Açıklama</label><textarea name="description" maxlength="1000" rows="2" placeholder="İçerik, miktar, uygulama veya teslimat bilgisi"></textarea>
<label>Fiyat birimi</label><select name="unit">@foreach(\App\Models\HotelExtra::UNITS as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select>
<label class="chk"><input type="checkbox" name="available_after_booking" value="1" checked> Rezervasyon sonrasında da talep edilebilir</label><button class="btn">Kataloğa ekle</button></form></section>
@endif

@if($section === 'campaigns')
<section id="hotel-campaigns" class="hotel-editor-section hotel-editor-major"><div class="hotel-editor-section-head"><span class="hotel-editor-section-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M20 12.5 12.5 20a2.1 2.1 0 0 1-3 0L3 13.5V4h9.5l7.5 7.5a.7.7 0 0 1 0 1ZM7.5 8.5h.01"/></svg></span><div><h2>Kampanyalar</h2><p>Uzun konaklamalara otomatik indirim kuralları belirleyin.</p></div><span class="hotel-editor-step">04</span></div><p class="sub">Oda ücretine uygulanır; birden fazla kural varsa gece sayısına uyan en yüksek indirim geçerlidir.</p>
@foreach($hotel->discounts->sortBy('min_nights') as $dc)
<div class="cardbox inl">{{ $dc->min_nights }}+ gece: <b>%{{ $dc->percent }} indirim</b>
<form method="post" action="{{ route('panel.discounts.destroy', [$hotel, $dc]) }}" style="display:inline">@csrf @method('DELETE')<button class="btn sm ghost">Sil</button></form></div>
@endforeach
<form class="form wide" method="post" action="{{ route('panel.discounts.store', $hotel) }}">@csrf<h3>Yeni kampanya</h3>
<div class="row2"><div><label>En az gece</label><input type="number" name="min_nights" min="2" max="60" value="7" required></div><div><label>İndirim (%)</label><input type="number" name="percent" min="1" max="50" value="10" required></div></div>
<button class="btn">Kampanya ekle</button></form></section>
@endif
</div>
<script>
(()=>{const city=document.getElementById('hotel-city-edit'),district=document.getElementById('hotel-district-edit');if(!city||!district)return;function filter(){const id=city.value;let first=null;[...district.options].forEach(o=>{const show=o.dataset.city===id;o.hidden=!show;o.disabled=!show;if(show&&!first)first=o});if(!district.value||district.selectedOptions[0]?.disabled)district.value=first?.value||'';}city.addEventListener('change',filter);filter();})();
</script>
@endsection
