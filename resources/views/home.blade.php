@extends('layouts.app')
@php
    $sehir   = \App\Models\Setting::al('site_city', 'Antalya');
    $sehirSlug = \Illuminate\Support\Str::slug($sehir);
    $siteMarka = \App\Models\Setting::al('site_name', config('app.name', 'Pet Otel'));
    $siteBolge = trim(\App\Models\Setting::al('site_district', '') . ($sehir !== '' ? ', ' . $sehir : ''), ', ');
    $adet    = $hoteller->count();
    $ilceAd  = $ilceler->count();
    $kirinti = [['ad' => 'Ana Sayfa']];

    $anaSoru = ($siteBolge ?: $sehir) . ' bölgesinde kedi ve köpek konaklaması sunan tesisleri oda tipi, günlük fiyat ve '
        . 'giriş-çıkış saatleriyle birlikte karşılaştırın. ' . $adet . ' aktif tesis, '
        . $ilceAd . ' ilçe ve '
        . ($enUcuz !== null
            ? number_format($enUcuz, 0, ',', '.') . ' TL’den başlayan günlük ücretlerle.'
            : 'günlük ücretleriyle.');


    /* Yalnızca aktif tesislerin kendi kayıtlarında bildirdiği hizmetleri
     * göster; bu liste tüm tesislerde sunuluyormuş izlenimi vermesin. */
    $ikonlar = [];
    $hizmetEtiketleri = [];
    foreach ($hoteller as $tesis) {
        $hizmetler = json_decode((string) ($tesis->amenities ?? ''), true);
        if (!is_array($hizmetler)) $hizmetler = preg_split('/[,;\r\n]+/', (string) ($tesis->amenities ?? '')) ?: [];
        foreach ($hizmetler as $hizmet) {
            $hizmet = trim((string) $hizmet);
            if ($hizmet !== '') $hizmetEtiketleri[mb_strtolower($hizmet, 'UTF-8')] = $hizmet;
        }
    }
    foreach (array_slice(array_values($hizmetEtiketleri), 0, 14) as $hizmet) {
        $aranan = mb_strtolower($hizmet, 'UTF-8');
        $ikon = match (true) {
            str_contains($aranan, 'veteriner') => 'stethoscope',
            str_contains($aranan, 'kamera') => 'camera',
            str_contains($aranan, 'video') => 'video',
            str_contains($aranan, 'transfer'), str_contains($aranan, 'taksi') => 'van',
            str_contains($aranan, 'eğitim') => 'dog',
            str_contains($aranan, 'oyun'), str_contains($aranan, 'park'), str_contains($aranan, 'bahçe') => 'ball',
            str_contains($aranan, 'bakım'), str_contains($aranan, 'konaklama') => 'heart',
            default => 'paw',
        };
        $ikonlar[] = ['i' => $ikon, 'b' => $hizmet];
    }

    /* kayan kart şeridi: tamamı DB'den */
    $seritKart = [];
    $seritKart[] = ['i' => 'building', 'b' => $adet . ' pet otel',
                    's' => $sehir . ' genelinde listelenen aktif tesis sayısı.'];
    $seritKart[] = ['i' => 'pin', 'b' => $ilceAd . ' ilçe',
                    's' => $ilceler->pluck('name')->implode(', ') . '.'];
    if ($enUcuz !== null) {
        $seritKart[] = ['i' => 'currency', 'b' => number_format($enUcuz, 0, ',', '.') . ' TL’den başlayan',
                        's' => 'Günlük en düşük oda ücreti; işletmelerden alınmış.'];
    }
    $seritKart[] = ['i' => 'paw', 'b' => 'Kedi ve köpek odaları',
                    's' => 'Oda tipi, ölçü, kapasite ve fiyat oda bazında listelenir.'];
    $seritKart[] = ['i' => 'clock', 'b' => 'Giriş-çıkış saatleri',
                    's' => 'Tesisin paylaştığı giriş-çıkış saatleri detay sayfasında yer alır.'];
    $seritKart[] = ['i' => 'map', 'b' => 'Konum ve harita',
                    's' => 'Tesis konumu ve varsa harita bağlantısı detay sayfasında yer alır.'];

    /* oda seridi: en yüksek fiyatlı 10 oda tipi
     * N+1 olmasin diye hotel iliski tek seferde yukleniyor. */
    $odalar = \App\Models\RoomType::with('hotel')
        ->whereHas('hotel', fn($q) => $q->where('status', 'active'))
        ->orderByDesc('base_price')->limit(10)->get();

    /* son yazılar */
    $yazilar = \App\Models\Post::whereNotNull('published_at')
        ->orderByDesc('published_at')->limit(6)->get();

    $ogeler = [];
    foreach ($hoteller as $h) $ogeler[] = ['url' => route('hotels.show', $h->slug), 'ad' => $h->name];
@endphp

@section('title', 'Antalya Pet Otel - Antalya Köpek Oteli - Antalya Kedi Oteli')
@section('desc', \App\Models\Setting::al('meta_description', seo_metin($anaSoru, 158)))
@section('ogtitle', 'Antalya Pet Otel - Antalya Köpek Oteli - Antalya Kedi Oteli')
@section('ogdesc', \App\Models\Setting::al('meta_description', seo_metin($anaSoru, 200)))
@push('head')
{!! seo_liste_sema($sehir . ' Pet Otelleri', url()->current(), $anaSoru, $ogeler) !!}
{!! sema_kirinti($kirinti) !!}
{!! sema_sss($sss) !!}
@endpush

@php
    $heroPrefix = \App\Models\Setting::al('home_hero_prefix', 'Minik dostunuz için');
    $heroHighlight = \App\Models\Setting::al('home_hero_highlight', 'doğru pet oteli');
    $heroSuffix = \App\Models\Setting::al('home_hero_suffix', 'bulun.');
    $heroDescription = \App\Models\Setting::al('home_hero_description', 'Tesislerin oda, hizmet ve iletişim bilgilerini karşılaştırın. Dostunuz için uygun konaklamayı kolayca seçin.');
    $heroMedia = \App\Models\Setting::al('home_hero_image', '');
    // Kurulumdan yeni çıkan sitelerde Antalya'ya özgü örnek fotoğrafı
    // başka şehir veya işletme fotoğrafı gibi göstermeyin.
    $heroImageUrl = $heroMedia ? \Illuminate\Support\Facades\Storage::disk('public')->url($heroMedia) : null;
    $homeHotelTitle = \App\Models\Setting::al('home_hotels_title', $sehir . ' bölgesindeki tüm pet otelleri');
    $homeHotelDescription = \App\Models\Setting::al('home_hotels_description', 'Fiyat, konum ve oda özelliklerini karşılaştırıp dostunuz için en uygun tesisi bulun.');
@endphp

@section('content')

{{-- ============================================================
     1) HERO — tab seridi + arama
     ============================================================ --}}
@php ob_start(); $homeBlocks = []; @endphp
<section class="home-hero-shell" @if($heroImageUrl) style="--home-hero-image: url('{{ $heroImageUrl }}')" @endif>
  <div class="wrap home-hero-wrap">
    @php
      $heroOtel = $hoteller->first();
      $heroOtelIkinci = $hoteller->skip(1)->first();
      $heroHotelPhoto = $heroOtel?->primaryPhotoUrl();
      $heroHasImage = $heroHotelPhoto !== null;
      $heroPhotoRepresentative = $heroOtel?->hasRepresentativeCover() ?? false;
      $heroPhotoUrl = $heroHasImage ? $heroHotelPhoto : ($heroImageUrl ?: asset('img/paw-placeholder.svg'));
      $heroPhotoAlt = $heroHasImage ? $heroOtel->name . ' pet oteli fotoğrafı' . ($heroPhotoRepresentative ? ', temsili görünüm; tesis fotoğrafı paylaşılmadı' : '') : ($heroImageUrl ? $siteMarka . ' kapak görseli' : 'Pati illüstrasyonu; henüz tesis fotoğrafı eklenmedi');
    @endphp
    <div class="home-hero-grid">
      <div class="home-hero-copy">
        <span class="home-eyebrow"><i></i> {{ mb_strtoupper($sehir, 'UTF-8') }} BÖLGESİNDE HAYVAN DOSTU KONAKLAMA</span>
        <h1 class="pw-h1">{{ $heroPrefix }} <em>{{ $heroHighlight }}</em> {{ $heroSuffix }}</h1>
        <p class="pw-alt">{{ $heroDescription }}</p>
        <nav class="pw-tabs" aria-label="Konaklama türü">
          <a class="on" href="{{ route('hotels.all') }}">🐾 Pet otel</a>
          <a href="{{ route('hotels.dogs') }}">🐕 Köpek otelleri</a>
          <a href="{{ route('hotels.cats') }}">🐈 Kedi otelleri</a>
        </nav>
      </div>
      <div class="home-hero-art" aria-label="{{ $siteBolge ?: $sehir }} bölgesinde evcil hayvan dostu otel">
        <figure class="home-hero-photo home-hero-photo-main">
          <img src="{{ $heroPhotoUrl }}" alt="{{ $heroPhotoAlt }}" fetchpriority="high" decoding="async" width="900" height="650">
          <figcaption>
            @if($heroHasImage)
              {{ $heroOtel->name }}
              <small>{{ $heroOtel->district?->name ?? $sehir }}@if($heroPhotoRepresentative) · Temsili görsel · Tesis fotoğrafı paylaşılmadı @endif</small>
            @else
              {{ $heroImageUrl ? $siteMarka . ' · ' . ($siteBolge ?: $sehir) : 'Tesis görseli henüz eklenmedi' }}
            @endif
          </figcaption>
          <div class="home-hero-image-proof"><span aria-hidden="true">✦</span><span><strong>{{ $hoteller->count() }} tesis</strong><small>{{ $sehir }} bölgesinde keşfedilmeyi bekliyor</small></span></div>
        </figure>
      </div>
    </div>
  <form class="pw-search" id="home-search" action="{{ route('search') }}" method="get" role="search">
    <div class="sf">
      <label for="f-ilce" class="sr-only">Bölge</label>
      <span class="ic" aria-hidden="true">&#128205;</span>
      <select name="ilce" id="f-ilce">
        <option value="">Tüm bölgeler</option>
        @foreach($ilceler as $d)
          <option value="{{ $d->slug }}" @selected(request('ilce') === $d->slug)>
            {{ $d->name }} ({{ $d->hotels->count() }})
          </option>
        @endforeach
      </select>
    </div>
    <div class="sf">
      <label for="f-giris" class="sr-only">Giriş tarihi</label>
      <span class="ic" aria-hidden="true">&#128337;</span>
      <input type="date" id="f-giris" name="giris" value="{{ request('giris', today()->toDateString()) }}" min="{{ today()->toDateString() }}" required>
    </div>
    <div class="sf">
      <label for="f-cikis" class="sr-only">Çıkış tarihi</label>
      <span class="ic" aria-hidden="true">&#128337;</span>
      <input type="date" id="f-cikis" name="cikis" value="{{ request('cikis') }}" min="{{ today()->addDay()->toDateString() }}" required>
    </div>
    <div class="sf">
      <label for="f-kapasite" class="sr-only">Evcil hayvan sayısı</label>
      <span class="ic" aria-hidden="true">&#128062;</span>
      <select name="kapasite" id="f-kapasite">
        <option value="">Dost sayısı</option>
        @foreach([1, 2, 3, 4] as $petCount)
          <option value="{{ $petCount }}" @selected((int) request('kapasite') === $petCount)>{{ $petCount }} dost</option>
        @endforeach
      </select>
    </div>
    <div class="sf">
      <label for="f-tur" class="sr-only">Evcil hayvan türü</label>
      <span class="ic" aria-hidden="true">&#128054;</span>
      <select name="tur" id="f-tur">
        <option value="">Köpek ve kedi</option>
        <option value="Köpek" @selected(request('tur') === 'Köpek')>Köpek</option>
        <option value="Kedi"  @selected(request('tur') === 'Kedi')>Kedi</option>
      </select>
    </div>
    <div class="sf">
      <label for="f-fiyat" class="sr-only">Gecelik fiyat aralığı</label>
      <span class="ic" aria-hidden="true">₺</span>
      <select name="fiyat" id="f-fiyat">
        <option value="">Tüm fiyatlar</option>
        <option value="0-700" @selected(request('fiyat') === '0-700')>700 ₺’ye kadar</option>
        <option value="700.01-1000" @selected(request('fiyat') === '700.01-1000')>700,01–1.000 ₺</option>
        <option value="1000.01-1500" @selected(request('fiyat') === '1000.01-1500')>1.000,01–1.500 ₺</option>
        <option value="1500.01-99999" @selected(request('fiyat') === '1500.01-99999')>1.500 ₺ üzeri</option>
      </select>
    </div>
    <div class="sf" id="f-size-wrap" @if(request('tur') !== 'Köpek') hidden @endif>
      <label for="f-size" class="sr-only">Köpek boyutu</label>
      <span class="ic" aria-hidden="true">🐕</span>
      <select name="size" id="f-size" @disabled(request('tur') !== 'Köpek')>
        <option value="" @selected(!request('size'))>Tüm boylar</option>
        <option value="small" @selected(request('size') === 'small')>Küçük ırk</option>
        <option value="medium" @selected(request('size') === 'medium')>Orta ırk</option>
        <option value="large" @selected(request('size') === 'large')>Büyük ırk</option>
      </select>
    </div>
    <button class="btn" type="submit">Ara</button>
  </form>
  <script>
  (function(){
    var checkIn=document.getElementById('f-giris'), checkOut=document.getElementById('f-cikis');
    if(!checkIn||!checkOut)return;
    var petType=document.getElementById('f-tur'), petSize=document.getElementById('f-size'), sizeWrap=document.getElementById('f-size-wrap');
    function syncPetSize(){if(!petType||!petSize||!sizeWrap)return;var dogsOnly=petType.value==='Köpek';sizeWrap.hidden=!dogsOnly;petSize.disabled=!dogsOnly;if(!dogsOnly)petSize.value=''}
    if(petType){petType.addEventListener('change',syncPetSize);syncPetSize()}
    function isoTomorrow(value){var d=new Date(value+'T00:00:00');d.setDate(d.getDate()+1);return d.getFullYear()+'-'+String(d.getMonth()+1).padStart(2,'0')+'-'+String(d.getDate()).padStart(2,'0')}
    function syncDates(){var now=new Date(),today=now.getFullYear()+'-'+String(now.getMonth()+1).padStart(2,'0')+'-'+String(now.getDate()).padStart(2,'0');checkIn.min=today;if(!checkIn.value||checkIn.value<today)checkIn.value=today;var minOut=isoTomorrow(checkIn.value);checkOut.min=minOut;if(!checkOut.value||checkOut.value<minOut)checkOut.value=minOut}
    [checkIn,checkOut].forEach(function(input){input.addEventListener('click',function(){if(typeof input.showPicker==='function'){try{input.showPicker()}catch(e){}}});});
    checkIn.addEventListener('change',syncDates);syncDates();
  })();
  </script>
  </div>
  <div class="home-status-strip" id="homeStatusStrip" @if($dolulukOrani === null || $dolulukOrani < 1) hidden @endif aria-label="{{ $sehir }} güncel hava tahmini ve kayıtlı pet oteli doluluk özeti">
    <section class="home-status-card home-weather-card" id="homeWeather" hidden aria-label="{{ $sehir }} güncel sıcaklık ve rüzgâr tahmini" aria-live="polite" aria-busy="true">
      <span class="home-status-icon" aria-hidden="true">☀</span>
      <div class="home-status-copy">
        <span class="home-status-label">{{ mb_strtoupper($sehir, 'UTF-8') }}</span>
        <strong class="home-weather-temp">--°</strong>
      </div>
      <div class="home-status-detail"><span>Rüzgâr</span><strong class="home-weather-wind">-- km/sa</strong></div>
      <small class="home-weather-updated"><a href="https://api.met.no/weatherapi/locationforecast/2.0/documentation" target="_blank" rel="noopener noreferrer">MET Norway</a> <span class="home-weather-freshness">· veri bekleniyor</span></small>
    </section>
    <section class="home-status-card home-occupancy-card" id="homeOccupancy" @if($dolulukOrani === null || $dolulukOrani < 1) hidden @endif aria-label="Önümüzdeki yedi günlük ortalama oda doluluğu; kayıtlı bekleyen ve onaylı site rezervasyonları ile aktif oda envanterine göre. Harici kanallardaki rezervasyonları kapsamaz." aria-live="polite" aria-busy="true" title="Doluluk yalnızca Antalya Pet Otel platformunda kayıtlı rezervasyon ve oda envanterine göre hesaplanır; dış kanallardaki rezervasyonlar dahil değildir.">
      <span class="home-status-icon" aria-hidden="true">▦</span>
      <div class="home-status-copy">
        <span class="home-status-label">7 GÜNLÜK DOLULUK</span>
        <strong class="home-occupancy-value">{{ $dolulukOrani !== null ? '%' . $dolulukOrani : '—' }}</strong>
      </div>
      @if($dolulukOrani !== null)
        <div class="home-occupancy-meter" role="img" aria-label="Kayıtlı doluluk yüzde {{ $dolulukOrani }}">
          <span style="width:{{ $dolulukOrani }}%"></span>
        </div>
      @endif
      <small class="home-occupancy-note" title="Hesaplama zamanı: {{ $dolulukGuncellemeAt }}">{{ $dolulukOdaSayisi > 0 ? $dolulukOdaSayisi . ' oda · site verisi' : 'Oda verisi yok' }}</small>
    </section>
  </div>
  <script>
  (function(){
    window.homeStatusSync=function(){var s=document.getElementById('homeStatusStrip');if(!s)return;s.hidden=!s.querySelector('.home-status-card:not([hidden])');};
    var card=document.getElementById('homeWeather');
    if(!card)return;
    var temp=card.querySelector('.home-weather-temp');
    var wind=card.querySelector('.home-weather-wind');
    var freshness=card.querySelector('.home-weather-freshness');
    var loading=false;
    function compass(degrees){return ['K','KD','D','GD','G','GB','B','KB'][Math.round(degrees/45)%8]}
    function loadWeather(){
      if(loading)return;loading=true;
      fetch(@json(route('weather.antalya')),{headers:{'Accept':'application/json'},cache:'no-store'})
        .then(function(response){if(!response.ok)throw new Error('weather unavailable');return response.json()})
        .then(function(data){
          if(!data.available)throw new Error('weather data unavailable');
          temp.textContent=data.temperatureC+'°';
          wind.textContent=data.windKmh+' km/sa · '+compass(data.windDirection);
          freshness.textContent=data.stale?'· son başarılı tahmin':'· güncel tahmin';
          card.hidden=false;if(window.homeStatusSync)window.homeStatusSync();
          card.title='MET Norway hava tahmini; gösterilen değerler Antalya Pet Otel tarafından dönüştürülmüştür.';
          card.setAttribute('aria-busy','false');
        })
        .catch(function(){
          temp.textContent='—';
          wind.textContent='—';
          freshness.textContent='· veri alınamadı';
          card.hidden=true;if(window.homeStatusSync)window.homeStatusSync();
          card.setAttribute('aria-busy','false');
        })
        .finally(function(){loading=false;});
    }
    loadWeather();
    window.setInterval(loadWeather,10*60*1000);
  })();
  </script>
  <script>
  (function(){
    var card=document.getElementById('homeOccupancy');
    if(!card)return;
    var value=card.querySelector('.home-occupancy-value');
    var note=card.querySelector('.home-occupancy-note');
    var meter=card.querySelector('.home-occupancy-meter');
    var loading=false;
    function refreshOccupancy(){
      if(loading)return;loading=true;
      fetch(@json(route('occupancy.antalya')),{headers:{'Accept':'application/json'},cache:'no-store'})
        .then(function(response){if(!response.ok)throw new Error('occupancy unavailable');return response.json()})
        .then(function(data){
          if(!data.available||data.occupancyPercent===null){
            card.hidden=true;if(window.homeStatusSync)window.homeStatusSync();
            value.textContent='—';
            note.textContent='Oda verisi yok';
            if(meter)meter.hidden=true;
            return;
          }
          var percent=Math.max(0,Math.min(100,Math.round(Number(data.occupancyPercent))));
          card.hidden=percent<1;if(window.homeStatusSync)window.homeStatusSync();
          value.textContent='%'+percent;
          note.textContent=data.roomCount+' oda · site verisi';
          note.title='Son hesaplama: '+data.updatedAt+'; önümüzdeki '+data.periodDays+' günün kayıtlı rezervasyonları.';
          if(meter){meter.hidden=false;meter.querySelector('span').style.width=percent+'%';meter.setAttribute('aria-label','Kayıtlı doluluk yüzde '+percent);}
          card.setAttribute('aria-label','Önümüzdeki '+data.periodDays+' günlük ortalama oda doluluğu: yüzde '+percent+'; '+data.roomCount+' kayıtlı oda ve site rezervasyonlarına göre. Harici kanallar dahil değildir.');
        })
        .catch(function(){})
        .finally(function(){card.setAttribute('aria-busy','false');loading=false;});
    }
    refreshOccupancy();
    window.setInterval(refreshOccupancy,5*60*1000);
  })();
  </script>
</section>

@php $homeBlocks['hero'] = ob_get_clean(); ob_start(); @endphp
{{-- ============================================================
     2) EN ÇOK GÖRÜNTÜLENEN OTELLER — yatay slider
     ============================================================ --}}
<section class="pw-bolum home-hotels-section"><div class="wrap">
  <div class="pw-bas">
    <div class="home-hotels-heading">
        <span class="home-hotels-kicker"><i aria-hidden="true">✦</i> {{ mb_strtoupper($sehir, 'UTF-8') }} BÖLGESİNDE KONAKLAMA SEÇENEKLERİ</span>
      {{-- Sıralama "en çok görüntülenen" DEĞİL: görüntülenme verisi
     yok. Gerçek ve doğrulanabilir ölçüt: günlük ücret, ucuzdan
     pahalıya. Alt satır hangi ölçütün kullanıldığını söylüyor. --}}
      <h2>{{ $homeHotelTitle }}</h2>
      <div class="alt">{{ $homeHotelDescription }}</div>
    </div>
    <div class="home-hotels-summary" aria-label="Liste özeti">
      <span><b>{{ $hoteller->count() }}</b> tesis</span>
      <span><b>{{ $ilceler->count() }}</b> ilçe</span>
      @if($enUcuz !== null)<span><b>{{ number_format($enUcuz, 0, ',', '.') }} ₺</b>’den başlayan</span>@endif
    </div>
  </div>
  <div class="grid" id="otelSerit">
    @foreach($hoteller as $h)
      @include('hotels._card', ['h' => $h])
    @endforeach
  </div>
</div></section>

@php $homeBlocks['hotels'] = ob_get_clean(); ob_start(); @endphp
@php $homeBlocks['features'] = ob_get_clean(); ob_start(); @endphp
{{-- ============================================================
     4) NASIL ÇALIŞIR — 4 adım
     ============================================================ --}}
<section class="pw-bolum"><div class="wrap">
  <h2>{{ $siteMarka }} rehberi nasıl çalışır?</h2>
  <div class="steps">
    <div class="step"><i>1</i><b>Tesisi seçin</b><p>İlçe ve hayvan türüne göre listeleyin; oda tipi ve günlük ücreti tek tabloda görün.</p></div>
    <div class="step"><i>2</i><b>Odayı inceleyin</b><p>Oda ölçüsü, kapasitesi, giriş-çıkış saati ve kabul edilen tür oda sayfasında yazılıdır.</p></div>
    <div class="step"><i>3</i><b>{{ $onlineBookingEnabled ? 'Çevrimiçi rezervasyon talebi gönderin' : 'İşletmeyle iletişime geçin' }}</b><p>{{ $onlineBookingEnabled ? 'Talep platforma kaydedilir; e-posta iletimi doğrulanamazsa uyarı gösterilir. İşletme onayı gelmeden rezervasyon kesinleşmez.' : 'Çevrimiçi rezervasyon talepleri şu anda alınmıyor; müsaitliği ve koşulları işletmeyle doğrudan teyit edin.' }}</p></div>
    <div class="step"><i>4</i><b>Ödemeyi otelde yapın</b><p>Ücret, tesiste yapılır. Site üzerinden ödeme alınmaz ve kart bilgisi istenmez.</p></div>
  </div>
</div></section>

@php $homeBlocks['steps'] = ob_get_clean(); ob_start(); @endphp
@include('home._pawlike')
@php $homeBlocks['pawlike'] = ob_get_clean(); ob_start(); @endphp

{{-- ============================================================
     5) ÖNE ÇIKAN ODA TİPLERİ — yatay slider
     ============================================================ --}}
@if($odalar->isNotEmpty())
<section class="pw-bolum"><div class="wrap">
  <div class="pw-bas">
    <div>
      <h2>Öne çıkan oda tipleri</h2>
      <div class="alt">İşletmelerin güncel günlük ücretleri; oda tipine göre en yüksek fiyattan başlayarak.</div>
    </div>
    <div class="pw-ok">
      <button type="button" onclick="document.getElementById('odaSerit').scrollBy({left:-340,behavior:'smooth'})" aria-label="Önceki odalar">&#8249;</button>
      <button type="button" onclick="document.getElementById('odaSerit').scrollBy({left:340,behavior:'smooth'})" aria-label="Sonraki odalar">&#8250;</button>
    </div>
  </div>
  <div class="pw-oda" id="odaSerit">
    @foreach($odalar as $o)
      <a class="oda-kart" href="{{ route('hotels.show', $o->hotel->slug) }}">
        <div class="g">
          @php
            $roomPhotoUrl = $o->hotel?->primaryPhotoUrl();
            $roomPhotoRepresentative = $o->hotel?->hasRepresentativeCover() ?? false;
          @endphp
          @if($roomPhotoUrl)
            <img src="{{ $roomPhotoUrl }}" alt="{{ $o->hotel->name }} - {{ $o->name }}{{ $roomPhotoRepresentative ? ', temsili görsel; tesis fotoğrafı paylaşılmadı' : '' }}" decoding="async" width="640" height="480">
            @if($roomPhotoRepresentative)<span class="apt-lcard__flag">Temsili görsel</span>@endif
          @else
            <span class="oda-image-placeholder" role="img" aria-label="{{ $o->hotel->name }} için tesis fotoğrafı henüz paylaşılmadı"><x-ui-icon name="camera" size="34" /></span>
          @endif
          <span class="kind {{ $o->pet_type === 'cat' ? 'cat' : 'dog' }}">{{ \App\Models\RoomType::PET_TYPES[$o->pet_type] ?? 'Evcil' }}</span>
        </div>
        <div class="b">
          <b>{{ $o->name }}</b>
          <div class="m">{{ $o->hotel->name }}{{ $o->size !== 'any' ? ' · ' . \App\Models\RoomType::SIZES[$o->size] : '' }} · {{ max(1,(int)$o->capacity) }} dost/gece</div>
          <div class="p">{{ number_format((float) $o->base_price, 0, ',', '.') }} ₺ <small>/ gece</small></div>
        </div>
      </a>
    @endforeach
  </div>
</div></section>
@endif

@php $homeBlocks['rooms'] = ob_get_clean(); ob_start(); @endphp
{{-- ============================================================
     6) SON YAZILAR — 2 kolon
     ============================================================ --}}
@if($yazilar->isNotEmpty())
<section class="pw-bolum home-blog-section"><div class="wrap">
  <div class="pw-bas">
    <div><h2>Son yazılar</h2></div>
    <div class="pw-ok">
      <a class="btn sm" href="{{ route('blog.index') }}">Tüm yazılar</a>
    </div>
  </div>
  <div class="pw-blog">
    @foreach($yazilar as $y)
      <a href="{{ route('blog.show', $y->slug) }}">
        <img src="{{ blog_cover_asset($y->slug) }}" alt="{{ $y->title }}" decoding="async" width="600" height="315" fetchpriority="low">
        <div class="m">
          <b>{{ $y->title }}</b>
          <span>{{ $y->published_at?->format('d.m.Y') }} · Pet Otel Rehberi</span>
        </div>
      </a>
    @endforeach
  </div>
</div></section>
@endif

@php $homeBlocks['blog'] = ob_get_clean(); ob_start(); @endphp
{{-- ============================================================
     7) İLÇELER
     ============================================================ --}}
<section class="pw-bolum"><div class="wrap">
  <h2>{{ $sehir }} ilçelerinde pet otel</h2>
  <div class="chips">
    @foreach($ilceler as $d)
      <a class="chip" href="{{ url('/' . $d->slug) }}">{{ $d->name }} ({{ $d->hotels->count() }})</a>
    @endforeach
  </div>
</div></section>

@php $homeBlocks['districts'] = ob_get_clean(); ob_start(); @endphp
{{-- ============================================================
     8) SSS
     ============================================================ --}}
<section class="pw-bolum"><div class="wrap faq">
  <h2>Sıkça sorulan sorular</h2>
  @foreach($sss as $i => $q)
    <details class="faq" @if($i === 0) open @endif><summary>{{ $q['soru'] }}</summary><p>{{ $q['cevap'] }}</p></details>
  @endforeach
  <p class="hint" style="margin-top:18px">Daha fazla soru için <a href="{{ route('pages.show', 'sss') }}">tüm sıkça sorulan sorular sayfasına</a> bakın.</p>
</div></section>

@php $homeBlocks['faq'] = ob_get_clean(); ob_start(); @endphp
{{-- ============================================================
     9) CTA
     ============================================================ --}}
@guest
<section class="pw-bolum"><div class="wrap"><div class="cta">
  <div>
    <h2>{{ $sehir }}’da pet otel işletiyor musunuz?</h2>
    <p>Tesisinizi oda seçenekleri, fiyatlar ve iletişim bilgileriyle tek bir sayfada tanıtın. Ziyaretçiler uygun konaklamayı karşılaştırıp doğrudan size ulaşsın.</p>
  </div>
  <a class="btn" href="{{ route('partner.create') }}">Otelinizi Yayınlayın</a>
</div></div></section>
@endguest
@php
    $homeBlocks['cta'] = ob_get_clean();
    $homeSectionConfig = json_decode((string) \App\Models\Setting::al('home_sections', ''), true) ?: [];
    $homeDefaultKeys = ['hero','hotels','features','steps','pawlike','rooms','blog','districts','faq','cta'];
    $homeOrder = array_values(array_intersect($homeSectionConfig['order'] ?? [], $homeDefaultKeys));
    $homeOrder = array_values(array_unique(array_merge($homeOrder, array_diff($homeDefaultKeys, $homeOrder))));
    $homeVisible = array_key_exists('visible', $homeSectionConfig) ? $homeSectionConfig['visible'] : $homeDefaultKeys;
    foreach ($homeOrder as $homeKey) if (in_array($homeKey, $homeVisible, true)) echo $homeBlocks[$homeKey] ?? '';
@endphp
@endsection
