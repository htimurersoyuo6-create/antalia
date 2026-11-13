@extends('layouts.app')
@php
    $ad    = $hotel->name;
    $galleryImages = collect($hotel->galeri ?? [])->values();
    $hasCoverImage = $hotel->hasCoverImage();
    $primaryPhotoUrl = $hasCoverImage ? $hotel->kapak_url : $galleryImages->first();
    $isFavorite = auth()->check() ? auth()->user()->favoriteHotels()->where('hotels.id', $hotel->id)->exists() : false;
    $ilce  = $hotel->district->name ?? 'Antalya';
    $sehir = $hotel->city->name ?? 'Antalya';
    $siteBrand = \App\Models\Setting::al('site_name', config('app.name', 'Pet Otel'));

    $am = [];
    $ham = trim((string) ($hotel->amenities ?? ''));
    if ($ham !== '') {
        $j = json_decode($ham, true);
        $am = is_array($j)
            ? array_values(array_filter(array_map(static fn($x) => ucfirst(trim(mb_strtolower((string) $x))), $j)))
            : array_values(array_filter(array_map('trim', explode(',', $ham))));
    }

    $fiyatlar = [];
    foreach ($hotel->rooms as $o) if ((float) $o->base_price > 0) $fiyatlar[] = (float) $o->base_price;
    $enUcuz = $fiyatlar ? min($fiyatlar) : null;

    $giris  = $hotel->check_in  ? date('H:i', strtotime((string) $hotel->check_in))  : null;
    $cikis  = $hotel->check_out ? date('H:i', strtotime((string) $hotel->check_out)) : null;

    $ozet = seo_metin($hotel->description
        ?: $ad . ' ' . $ilce . ' ilçesinde konaklama sunan bir pet otelidir. Oda tipleri, günlük fiyatlar ve giriş-çıkış saatleri sayfada listelenmiştir.', 158);

    $kirinti = [
        ['ad' => 'Ana Sayfa', 'url' => route('home')],
        ['ad' => $sehir . ' Pet Otelleri', 'url' => route('hotels.all')],
        ['ad' => $ilce . ' Pet Otelleri', 'url' => url('/' . ($hotel->district->slug ?? ''))],
        ['ad' => $ad],
    ];
@endphp
@section('title', $ad . ' | ' . $ilce . ' Pet Oteli | ' . $siteBrand)
@section('desc', $ozet)
@section('ogtitle', $ad . ' | ' . $sehir . ' Pet Oteli | ' . $siteBrand)
@section('ogdesc', $ozet)
@section('ogimage', $primaryPhotoUrl ?: asset('img/og-default.png'))
@push('head')
{!! seo_otel_sema($hotel, $kirinti, $sss, auth()->check()) !!}
@endpush
@section('body_class', 'hotel-detail-body')
@section('content')
<span hidden data-hotel-page="{{ $hotel->slug }}"></span>
@php
    $gm = $galleryImages->take(3)->values();
    $galleryThumbs = $hasCoverImage ? $gm : $gm->skip(1)->values();
    $bookingGallery = $hasCoverImage ? $galleryImages->take(2) : $galleryImages->skip(1)->take(2);
    $temsiliKapak = $hotel->hasRepresentativeCover();
    $kapakWebp = preg_replace('~\.jpe?g$~i', '.webp', (string) $hotel->cover_path);
    $kapakWebpUrl = $temsiliKapak && $kapakWebp && \Illuminate\Support\Facades\Storage::disk('public')->exists($kapakWebp) ? asset('storage/' . $kapakWebp) : null;
    $galeriToplam = ($hasCoverImage ? 1 : 0) + $galleryImages->count();
@endphp
<div class="wrap apt-detail hotel-detail-page">

<nav class="apt-crumb" aria-label="Sayfa yolu">
  <a href="{{ route('home') }}">Ana Sayfa</a><span aria-hidden="true">/</span>
  <a href="{{ route('hotels.all') }}">{{ $sehir }} Pet Otelleri</a><span aria-hidden="true">/</span>
  <a href="{{ url('/' . ($hotel->district->slug ?? '')) }}">{{ $ilce }}</a><span aria-hidden="true">/</span>
  <span aria-current="page">{{ $ad }}</span>
</nav>

<header class="apt-head">
  <div class="apt-head-top">
    <div>
      <span class="apt-eyebrow"><i aria-hidden="true">✦</i> {{ mb_strtoupper($sehir, 'UTF-8') }}’DA PET OTELİ</span>
      <h1 class="apt-title">{{ $ad }}</h1>
    </div>
    @auth
      <form method="post" action="{{ route('favorites.toggle', $hotel->slug) }}" style="margin:0">@csrf
        <button class="apt-btn apt-btn--ghost" type="submit" aria-pressed="{{ $isFavorite ? 'true' : 'false' }}">
          <span aria-hidden="true">{{ $isFavorite ? '♥' : '♡' }}</span> {{ $isFavorite ? 'Kaydedildi' : 'Favorilerime ekle' }}
        </button>
      </form>
    @else
      <a class="apt-btn apt-btn--ghost" href="{{ route('login') }}"><span aria-hidden="true">♡</span> Favorilere ekle</a>
    @endauth
  </div>

  <div class="apt-badges">
    @if($hotel->license_verified_at)<span class="apt-badge apt-badge--ok">✓ Ruhsat doğrulandı</span>@endif
    @if($hotel->accepts_dogs)<span class="apt-badge"><x-ui-icon name="dog" size="14" /> Köpek konaklaması</span>@endif
    @if($hotel->accepts_cats)<span class="apt-badge"><x-ui-icon name="cat" size="14" /> Kedi konaklaması</span>@endif
    @if($hotel->has_daycare)<span class="apt-badge"><x-ui-icon name="clock" size="14" /> Gündüz bakımı</span>@endif
    @if($enUcuz)<span class="apt-badge apt-badge--price">{{ number_format($enUcuz, 0, ',', '.') }} TL / geceden</span>@endif
  </div>

  <p class="apt-meta" aria-label="Tesis konum ve konaklama bilgileri">
    <span><x-ui-icon name="pin" size="14" /> {{ $ilce }}, {{ $sehir }}</span>
    @if($hotel->rating_count)<span class="apt-rate">★ {{ number_format($hotel->rating_avg, 1) }} ({{ $hotel->rating_count }} yorum)</span>@endif
    @if($giris)<span><x-ui-icon name="clock" size="14" /> Giriş {{ $giris }}</span>@endif
    @if($cikis)<span><x-ui-icon name="clock" size="14" /> Çıkış {{ $cikis }}</span>@endif
    @if($hotel->min_stay)<span><x-ui-icon name="calendar" size="14" /> En kısa {{ (int) $hotel->min_stay }} gece</span>@endif
  </p>
</header>

<div class="apt-layout">

  <div class="apt-main">

    {{-- GALERİ --}}
    @if($primaryPhotoUrl)
    <div class="apt-gallery">
      <div class="apt-gallery__hero">
        <img src="{{ $kapakWebpUrl ?? $primaryPhotoUrl }}"
             alt="{{ $ad }} - {{ $ilce }} pet otel{{ $temsiliKapak ? ', temsili kapak görseli' : '' }}"
             width="1376" height="768" fetchpriority="high" decoding="async">
        @if($temsiliKapak)<span class="apt-gallery__flag">Temsili görsel</span>@endif
        @if($galeriToplam > 1)
          <button type="button" class="apt-gallery__count" data-gallery-open>
            <span aria-hidden="true">▣</span> {{ $galeriToplam }} fotoğraf
          </button>
        @endif
      </div>
      @if($galleryThumbs->isNotEmpty())
      <div class="apt-gallery__strip">
        @foreach($galleryThumbs as $g)
          <button type="button" class="apt-gallery__thumb" data-gallery-open aria-label="{{ $ad }} görsel {{ $loop->iteration + 1 }}">
            <img src="{{ $g }}" alt="{{ $ad }} {{ $ilce }} pet otel görseli {{ $loop->iteration + 1 }}"
                 width="600" height="450" fetchpriority="low" decoding="async">
          </button>
        @endforeach
      </div>
      @endif
    </div>
    @else
      <div class="apt-gallery">
        <div class="apt-gallery__hero apt-gallery__empty" role="img" aria-label="{{ $ad }} için kapak görseli henüz yüklenmemiş">
          <x-ui-icon name="camera" size="38" />
          <span>Tesis fotoğrafı henüz paylaşılmadı</span>
        </div>
      </div>
    @endif

    {{-- HIZLI ÇİPLER --}}
    @if($am || $hotel->has_daycare || $hotel->license_verified_at)
    <div class="apt-chips">
      @if($hotel->license_verified_at)<span class="apt-chip apt-chip--soft">✓ Ruhsat doğrulandı</span>@endif
      @if($hotel->has_daycare)<span class="apt-chip apt-chip--soft">Gündüz bakımı</span>@endif
      @foreach(array_slice($am, 0, 5) as $a)<span class="apt-chip apt-chip--soft">{{ $a }}</span>@endforeach
    </div>
    @endif

    {{-- BÖLÜM GEZİNTİSİ --}}
    <nav class="apt-sectionnav" aria-label="Otel sayfası bölümleri">
      <a href="{{ route('hotel-chats.create',$hotel->slug) }}">✉ Soru sor</a>
      <a href="#otel-odalar">Odalar</a>
      @if($am)<a href="#otel-olanaklar">Olanaklar</a>@endif
      @if($hotel->policies)<a href="#otel-kurallar">Kurallar</a>@endif
      @if($sss)<a href="#otel-sss">Sıkça Sorulanlar</a>@endif
      @if($cograf->isNotEmpty())<a href="#otel-yakin">Yakındaki oteller</a>@endif
      @if(!empty($yazilar))<a href="#otel-rehberler">Rehberler</a>@endif
      <a href="#otel-yorumlar">Yorumlar</a>
      @if($ilceler->isNotEmpty())<a href="#otel-ilceler">İlçeler</a>@endif
    </nav>

    {{-- HAKKINDA --}}
    <section class="apt-card" aria-labelledby="otel-hakkinda">
      <div class="apt-card__head">
        <span class="apt-card__icon"><x-ui-icon name="home" size="18" /></span>
        <div>
          <h2 class="apt-card__title" id="otel-hakkinda">Tesis hakkında</h2>
          <p class="apt-card__sub">{{ $ilce }}, Antalya’da konaklama bilgileri ve iletişim.</p>
        </div>
      </div>
      <div class="apt-card__body">
        <article class="prose otel-aciklama">{!! makale_guvenli_html('<p>' . e(seo_metin($hotel->description, 2400, false)) . '</p>') !!}</article>
        @if($hotel->address)
          <div class="hotel-address-card"><span><x-ui-icon name="pin" size="18" /></span><div><small>TESİS KONUMU</small><p>{{ $hotel->address }}</p></div>@if($hotel->google_maps_url)<a href="{{ $hotel->google_maps_url }}" target="_blank" rel="noopener noreferrer">Haritada göster <b aria-hidden="true">↗</b></a>@endif</div>
        @endif
      </div>
    </section>

    {{-- TESİS GÖRSELLERİ --}}
    @if($hotel->galeri)
    <section class="apt-card" aria-labelledby="otel-galeri">
      <div class="apt-card__head">
        <span class="apt-card__icon"><x-ui-icon name="camera" size="18" /></span>
        <div>
          <h2 class="apt-card__title" id="otel-galeri">Tesis görselleri</h2>
          <p class="apt-card__sub">Kaynak: Google işletme profili · {{ count($hotel->galeri) }} fotoğraf</p>
        </div>
      </div>
      <div class="gal">
        <div class="gal-izgara">
          @foreach($hotel->galeri as $i => $g)
            @php
              /* galeri() accessor tam URL donuyor; WebP karsiligi
               * /storage/... yolundan turetilir. */
              $rel  = str_replace(asset('storage'), '', (string) $g);
              $relW = preg_replace('~\.(jpe?g|png)$~i', '.webp', $rel);
              $gW   = ($relW && $relW !== $rel && \Illuminate\Support\Facades\Storage::disk('public')->exists($relW))
                  ? asset('storage/' . $relW) : null;
            @endphp
            <div class="hotel-gallery-tile"><picture>
              @if($gW)<source srcset="{{ $gW }}" type="image/webp">@endif
              <img src="{{ $g }}" fetchpriority="low" decoding="async" width="800" height="600"
                   alt="{{ $ad }} {{ $ilce }} pet otel görseli {{ $i + 2 }}">
            </picture></div>
          @endforeach
        </div>
      </div>
    </section>
    @endif

    {{-- ODALAR --}}
    <section class="apt-card" aria-labelledby="otel-odalar" style="padding-bottom:16px">
      <div class="apt-card__head">
        <span class="apt-card__icon"><x-ui-icon name="book" size="18" /></span>
        <div>
          <h2 class="apt-card__title" id="otel-odalar">Oda tipleri ve gecelik fiyatlar</h2>
          <p class="apt-card__sub">Her dost için uygun odayı seçin; seçtiğiniz tarihlerdeki müsaitlik rezervasyon öncesinde yeniden kontrol edilir. · {{ $hotel->rooms->count() }} oda tipi</p>
        </div>
      </div>
      @if($hotel->room_info_status !== 'missing')
        @php
          $freshnessCopy = [
            'confirmed' => ['Doğrulandı', 'İşletme oda ve fiyat bilgilerini ' . ($hotel->room_info_confirmed_at?->format('d.m.Y') ?? '') . ' tarihinde kontrol etti. Seçtiğiniz tarihlerdeki müsaitlik ve son fiyat rezervasyon sırasında işletme tarafından ayrıca teyit edilir.', 'ok'],
            'changed' => ['Güncellik teyidi gerekiyor', 'Oda veya fiyat bilgileri son doğrulamadan sonra değişti. Rezervasyon öncesinde güncel fiyatı ve seçenekleri işletmeyle teyit edin.', 'warn'],
            'stale' => ['Güncellik teyidi gerekiyor', 'İşletmenin son oda/fiyat doğrulaması 30 günden eski. Güncel fiyatı ve seçenekleri işletmeyle teyit edin.', 'warn'],
            'unconfirmed' => ['Henüz doğrulanmadı', 'İşletme oda ve fiyat bilgilerini henüz doğrulamadı. Rezervasyon öncesinde güncel fiyatı ve seçenekleri işletmeyle teyit edin.', 'warn'],
          ];
          [$freshnessTitle, $freshnessText, $freshnessTone] = $freshnessCopy[$hotel->room_info_status];
        @endphp
        <aside class="hotel-room-freshness {{ $freshnessTone }}" role="status"><span aria-hidden="true">{{ $freshnessTone === 'ok' ? '✓' : '!' }}</span><div><b>{{ $freshnessTitle }}</b><p>{{ $freshnessText }}</p></div></aside>
      @endif
      <div class="hotel-public-room-grid">
      @forelse($hotel->rooms as $room)
        @php $unitCount = $room->inventoryUnits->count(); @endphp
        <article class="hotel-public-room-card">
          <div class="hotel-public-room-icon"><x-ui-icon name="paw" size="24" /></div>
          <div class="hotel-public-room-main">
            <h3>{{ $room->name }}</h3>
            <div class="hotel-public-room-facts"><span>{{ \App\Models\RoomType::PET_TYPES[$room->pet_type] ?? 'Evcil' }}</span>@if($room->pet_type === 'dog' && $room->size !== 'any')<span>{{ \App\Models\RoomType::SIZES[$room->size] ?? 'Belirtilmemiş' }} ırk</span>@endif<span>{{ max(1, (int) $room->capacity) }} dost kapasitesi</span><span>{{ $unitCount }} fiziksel oda</span></div>
            <small>Oda, rezervasyon tarihlerinize göre otomatik atanır.</small>
          </div>
          <div class="hotel-public-room-price"><strong>{{ number_format((float) $room->base_price, 0, ',', '.') }} ₺</strong><span>/ gece</span>@if($onlineBookingEnabled)<button type="button" class="hotel-select-room" data-booking-open data-booking-room-id="{{ $room->id }}">Bu odayı seç <span aria-hidden="true">→</span></button>@endif @if($hotel->phone)<a class="hotel-room-whatsapp" data-room-whatsapp data-room-id="{{ $room->id }}" data-room-name="{{ $room->name }}" data-pet-type="{{ \App\Models\RoomType::PET_TYPES[$room->pet_type] ?? 'Evcil hayvan' }}" data-room-size="{{ \App\Models\RoomType::SIZES[$room->size] ?? 'Her boy' }}" data-room-price="{{ number_format((float) $room->base_price, 0, '.', '') }}" href="https://wa.me/{{ \App\Support\PhoneNumber::whatsapp($hotel->phone) }}?text={{ rawurlencode('Merhaba '.$hotel->name.', '.$room->name.' odası için bilgi almak istiyorum.') }}" target="_blank" rel="noopener noreferrer" aria-label="{{ $room->name }} için WhatsApp ile bilgi alın"><x-ui-icon name="whatsapp" size="16" /> Bu oda için WhatsApp’tan sor</a>@endif</div>
        </article>
      @empty
        <div class="hotel-empty-rooms"><span aria-hidden="true">🐾</span><div><b>Oda bilgileri hazırlanıyor</b><p>Bu tesis henüz oda tipi eklemedi. Güncel seçenekleri doğrudan işletmeden öğrenebilirsiniz.</p></div></div>
      @endforelse
      </div>
    </section>
    <style>
    .booking-whatsapp-dates{margin:12px 0;padding:13px;border:1px solid #dcebe0;border-radius:14px;background:#f4faf5}.booking-whatsapp-dates>b,.booking-whatsapp-dates>small{display:block}.booking-whatsapp-dates>b{color:#276142;font-size:13px}.booking-whatsapp-dates>small{margin:3px 0 10px;color:#68796e;font-size:11px;line-height:1.5}.booking-whatsapp-dates>div{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:9px}.booking-whatsapp-dates label{display:grid;gap:5px;color:#627067;font-size:11px;font-weight:750}.booking-whatsapp-dates input{width:100%;min-width:0;padding:9px;border:1px solid #dfe7df;border-radius:9px;background:#fff;color:#28382e;font:inherit}.hotel-room-freshness{display:flex;align-items:flex-start;gap:11px;margin:0 0 16px;padding:13px 15px;border:1px solid #bfe2cf;border-radius:13px;background:#f1faf5;color:#215e41}.hotel-room-freshness>span{display:grid;place-items:center;flex:0 0 25px;height:25px;border-radius:50%;background:#dbf2e4;color:#218153;font-weight:900}.hotel-room-freshness b{display:block;font-size:13px}.hotel-room-freshness p{margin:3px 0 0;color:#547363;font-size:12px;line-height:1.5}.hotel-room-freshness.warn{border-color:#f1d9ae;background:#fff9ec;color:#79521a}.hotel-room-freshness.warn>span{background:#ffedca;color:#a86a0b}.hotel-room-freshness.warn p{color:#806b4d}
    .hotel-room-whatsapp{grid-column:1/3;display:flex;align-items:center;justify-content:center;gap:7px;min-height:35px;padding:0 12px;border:1px solid #cce8d8;border-radius:10px;background:#eff9f3;color:#18794e;text-decoration:none;font-size:11px;font-weight:800;white-space:nowrap}.hotel-room-whatsapp:hover{background:#e1f3e8;color:#12623f}
    @media(max-width:560px){.hotel-room-whatsapp{grid-column:1/4;grid-row:2;width:100%}}
    </style>

    {{-- YORUMLAR --}}
    <section class="apt-card" aria-labelledby="otel-yorumlar">
      <div class="apt-card__head">
        <span class="apt-card__icon"><x-ui-icon name="star" size="18" /></span>
        <div>
          <h2 class="apt-card__title" id="otel-yorumlar">Misafir yorumları</h2>
          <p class="apt-card__sub">Yorumlar yalnızca konaklamasını tamamlamış misafirlerden yayımlanır.</p>
        </div>
      </div>
      @if($reviews->isNotEmpty())
        <div class="hotel-review-grid">
        @foreach($reviews as $rv)
        <article class="hotel-review-card"><div class="hotel-review-top"><b class="stars" aria-label="{{ (int) $rv->rating }} yıldız">{{ str_repeat('★', (int) $rv->rating) }}</b><small>{{ ($rv->user && $rv->user->name ? \Illuminate\Support\Str::before($rv->user->name, ' ') : 'Misafir') }}@if($rv->created_at) · {{ $rv->created_at->format('d.m.Y') }}@endif</small></div>@if($rv->title)<h3>{{ $rv->title }}</h3>@endif<p>{{ $rv->comment }}</p></article>
        @endforeach
        </div>
      @else
        <div class="hotel-empty-note"><span><x-ui-icon name="heart" size="20" /></span><div><b>İlk misafir yorumları bekleniyor</b><p>Bu tesis için henüz yayımlanmış yorum yok. İlk değerlendirmeler tamamlanan konaklamalardan sonra burada yer alır.</p></div></div>
      @endif
    </section>

    {{-- OLANAKLAR --}}
    @if($am)
    <section class="apt-card" aria-labelledby="otel-olanaklar">
      <div class="apt-card__head">
        <span class="apt-card__icon"><x-ui-icon name="sparkles" size="18" /></span>
        <div>
          <h2 class="apt-card__title" id="otel-olanaklar">Hizmetler ve olanaklar</h2>
          <p class="apt-card__sub">Konaklama sırasında sunulan imkânları önceden inceleyin.</p>
        </div>
      </div>
      <div class="hotel-amenity-grid">@foreach($am as $a)<div class="hotel-amenity-item"><span><x-ui-icon name="check" size="15" /></span><b>{{ $a }}</b></div>@endforeach</div>
    </section>
    @endif

    {{-- KURALLAR --}}
    @if($hotel->policies)
    <section class="apt-card" aria-labelledby="otel-kurallar">
      <div class="apt-card__head">
        <span class="apt-card__icon"><x-ui-icon name="clock" size="18" /></span>
        <div>
          <h2 class="apt-card__title" id="otel-kurallar">Giriş, çıkış ve konaklama kuralları</h2>
          <p class="apt-card__sub">Planınızı yaparken bu tesisin koşullarını göz önünde bulundurun.</p>
        </div>
      </div>
      <div class="hotel-rules-content"><span class="hotel-rules-mark" aria-hidden="true">i</span><ul class="hotel-rules-list">@foreach(preg_split('/\R+/u', trim($hotel->policies)) as $kural)@if(trim($kural) !== '')<li>{{ preg_replace('/\b(\d+)-\1\b/u', '$1', trim($kural)) }}</li>@endif @endforeach</ul></div>
    </section>
    @endif

    {{-- TESİS BİLGİLERİ --}}
    <section class="apt-card" aria-labelledby="otel-bilgiler">
      <div class="apt-card__head">
        <span class="apt-card__icon"><x-ui-icon name="building" size="18" /></span>
        <div>
          <h2 class="apt-card__title" id="otel-bilgiler">Tesis bilgileri</h2>
          <p class="apt-card__sub">Konaklama öncesi hızlı özet</p>
        </div>
      </div>
      <div class="apt-specs">
        <div class="apt-specs__row"><span class="apt-specs__key">Tesis türü</span><span class="apt-specs__val">{{ $hotel->accepts_dogs && $hotel->accepts_cats ? 'Kedi ve köpek oteli' : ($hotel->accepts_cats ? 'Kedi oteli' : 'Köpek oteli') }}</span></div>
        <div class="apt-specs__row"><span class="apt-specs__key">Konum</span><span class="apt-specs__val">{{ $ilce }}, {{ $sehir }}</span></div>
        <div class="apt-specs__row"><span class="apt-specs__key">Giriş saati</span><span class="apt-specs__val">{{ $giris ?? 'İşletmeye sorun' }}</span></div>
        <div class="apt-specs__row"><span class="apt-specs__key">Çıkış saati</span><span class="apt-specs__val">{{ $cikis ?? 'İşletmeye sorun' }}</span></div>
        <div class="apt-specs__row"><span class="apt-specs__key">En kısa konaklama</span><span class="apt-specs__val">{{ $hotel->min_stay ? (int) $hotel->min_stay . ' gece' : 'Belirtilmemiş' }}</span></div>
        <div class="apt-specs__row"><span class="apt-specs__key">Oda tipi</span><span class="apt-specs__val">{{ $hotel->rooms->count() }} seçenek</span></div>
        <div class="apt-specs__row"><span class="apt-specs__key">Başlangıç fiyatı</span><span class="apt-specs__val">{{ $enUcuz ? number_format($enUcuz, 0, ',', '.') . ' ₺ / gece' : 'İşletmeye sorun' }}</span></div>
        <div class="apt-specs__row"><span class="apt-specs__key">Ruhsat doğrulaması</span><span class="apt-specs__val">{{ $hotel->license_verified_at ? 'Doğrulandı' : 'Doğrulanmadı' }}</span></div>
      </div>
    </section>

    {{-- YAKINDAKİ OTELler --}}
    @if($cograf->isNotEmpty())
    <section class="apt-card" aria-labelledby="otel-yakin">
      <div class="apt-card__head">
        <span class="apt-card__icon"><x-ui-icon name="pin" size="18" /></span>
        <div>
          <h2 class="apt-card__title" id="otel-yakin">{{ $cograf->contains(fn ($c) => $c->district_id === $hotel->district_id) ? $ilce . ' ve çevresindeki pet otelleri' : $sehir . '’daki diğer pet oteller' }}</h2>
          <p class="apt-card__sub">Bölgedeki diğer tesisleri karşılaştırarak dostunuza uygun seçeneği bulun.</p>
        </div>
      </div>
      <div class="hotel-related-grid">
      @foreach($cograf as $c)
        <a class="hotel-related-card" href="{{ route('hotels.show', $c->slug) }}"><span class="hotel-related-icon"><x-ui-icon name="paw" size="19" /></span><span class="hotel-related-copy"><b>{{ $c->name }}</b><small>{{ $c->district->name ?? $ilce }} · Antalya</small></span><span class="hotel-related-arrow" aria-hidden="true">↗</span></a>
      @endforeach
      </div>
    </section>
    @endif

    {{-- REHBERLER --}}
    @if(!empty($yazilar))
    <section class="apt-card" aria-labelledby="otel-rehberler">
      <div class="apt-card__head">
        <span class="apt-card__icon"><x-ui-icon name="book" size="18" /></span>
        <div>
          <h2 class="apt-card__title" id="otel-rehberler">{{ $ilce }} pet oteli rehberleri</h2>
          <p class="apt-card__sub">Konaklama ve dostunuzun sağlığı için faydalı içerikler.</p>
        </div>
      </div>
      <div class="hotel-guide-grid">
      @foreach($yazilar as $y)
        <a class="hotel-guide-card" href="{{ route('blog.show', $y->slug) }}"><span class="hotel-guide-label">OKUMA ÖNERİSİ</span><b>{{ $y->title }}</b><span class="hotel-guide-link">Rehberi oku <i aria-hidden="true">→</i></span></a>
      @endforeach
      </div>
    </section>
    @endif

    {{-- İLÇELER --}}
    @if($ilceler->isNotEmpty())
    <section class="apt-card" aria-labelledby="otel-ilceler">
      <div class="apt-card__head">
        <span class="apt-card__icon"><x-ui-icon name="map" size="18" /></span>
        <div>
          <h2 class="apt-card__title" id="otel-ilceler">Antalya’nın diğer ilçelerinde pet oteller</h2>
          <p class="apt-card__sub">Aramanızı yakın ilçelerdeki konaklama seçenekleriyle genişletin.</p>
        </div>
      </div>
      <nav class="hotel-district-links" aria-label="Antalya ilçeleri">@foreach($ilceler as $il)<a href="{{ url('/' . $il->slug) }}">{{ $il->name }}<span aria-hidden="true">↗</span></a>@endforeach</nav>
    </section>
    @endif

    {{-- SSS --}}
    @if($sss)
    <section class="apt-card" aria-labelledby="otel-sss">
      <div class="apt-card__head">
        <span class="apt-card__icon"><x-ui-icon name="help" size="18" /></span>
        <div>
          <h2 class="apt-card__title" id="otel-sss">{{ $ad }} hakkında sıkça sorulanlar</h2>
          <p class="apt-card__sub">Tesis ve konaklama süreciyle ilgili kısa yanıtlar.</p>
        </div>
      </div>
      <div class="hotel-faq-list">
      @foreach($sss as $q)
        @php
          $kisaSoru = $q['soru'];
          if (\Illuminate\Support\Str::startsWith($kisaSoru, $ad . ' ')) {
              $kisaSoru = mb_substr($kisaSoru, mb_strlen($ad) + 1);
              $ilk = mb_substr($kisaSoru, 0, 1);
              $kisaSoru = ($ilk === 'i' ? 'İ' : mb_strtoupper($ilk)) . mb_substr($kisaSoru, 1);
          }
        @endphp
        <details class="hotel-faq-item"><summary><span>{{ $kisaSoru }}</span><i aria-hidden="true">+</i></summary><div class="hotel-faq-answer">{{ $q['cevap'] }}</div></details>
      @endforeach
      </div>
    </section>
    @endif

    {{-- TALEP --}}
    @if($hotel->sahipsizMi())
    <section class="hotel-claim-banner"><span class="hotel-claim-icon"><x-ui-icon name="building" size="22" /></span><div><span class="detail-eyebrow">TESİS YETKİLİSİ MİSİNİZ?</span><h2>İşletme bilgilerinizi kendiniz yönetin</h2><p>Oda, fiyat, görsel ve rezervasyon bilgilerini güncel tutmak için işletmenizi sahiplenin.</p></div><a href="{{ route('claims.create', $hotel->slug) }}">İşletmeyi sahiplen <span aria-hidden="true">→</span></a></section>
    @endif

  </div>

  {{-- YAN KOLON --}}
  <aside class="apt-side" aria-label="İletişim ve rezervasyon">

    <div class="apt-seller">
      <div class="apt-seller__head">
        <span class="apt-seller__avatar"><x-ui-icon name="building" size="20" /></span>
        <div>
          <p class="apt-seller__name">{{ $ad }}</p>
          <p class="apt-seller__sub">
            @if($hotel->license_verified_at)✓ Ruhsat doğrulandı @else ● Doğrudan işletme iletişimi @endif
          </p>
        </div>
      </div>

      <div class="apt-seller__price">
        @if($enUcuz)
          <b>{{ number_format($enUcuz, 0, ',', '.') }} ₺</b><small>/ gece</small>
        @else
          <b>Fiyat için arayın</b>
        @endif
      </div>

      <p class="apt-seller__note">{{ $onlineBookingEnabled ? 'Talep platforma kaydedilir; e-posta iletimi doğrulanamazsa uyarı gösterilir. Rezervasyon işletme onayı olmadan kesinleşmez.' : 'Çevrimiçi rezervasyon talepleri şu anda alınmıyor. Müsaitlik için işletmeyle doğrudan iletişim kurun.' }}</p>

      <div class="apt-seller__actions">
        @if($onlineBookingEnabled)
          <button type="button" class="apt-btn apt-btn--full" data-booking-open>Rezervasyon talebi gönder</button>
        @endif
        @if($hotel->phone)
          <a class="apt-btn apt-btn--ghost apt-btn--full" data-hotel-phone-call href="tel:{{ preg_replace('/[^0-9+]/', '', $hotel->phone) }}">
            <x-ui-icon name="phone" size="16" /> {{ $hotel->phone }}
          </a>
          <a class="apt-btn apt-btn--ok apt-btn--full hotel-whatsapp-link"
             href="https://wa.me/{{ \App\Support\PhoneNumber::whatsapp($hotel->phone) }}?text={{ rawurlencode('Merhaba '.$hotel->name.', tesisiniz hakkında bilgi almak istiyorum.') }}"
             target="_blank" rel="noopener noreferrer">
            <x-ui-icon name="whatsapp" size="16" /> WhatsApp ile yaz
          </a>
        @endif
        @auth
          <a class="apt-btn apt-btn--ghost apt-btn--full" href="{{ route('hotel-chats.create',$hotel->slug) }}"><x-ui-icon name="message" size="16" /> Tesise mesaj gönder</a>
        @endauth
      </div>

      <ul class="apt-seller__facts">
        <li><x-ui-icon name="pin" size="14" /> <span>{{ $ilce }}, {{ $sehir }}</span></li>
        @if($giris)<li><x-ui-icon name="clock" size="14" /> Giriş <b>{{ $giris }}</b></li>@endif
        @if($cikis)<li><x-ui-icon name="clock" size="14" /> Çıkış <b>{{ $cikis }}</b></li>@endif
        @if($hotel->min_stay)<li><x-ui-icon name="calendar" size="14" /> En kısa <b>{{ (int) $hotel->min_stay }} gece</b></li>@endif
        @if($hotel->rating_count)<li><x-ui-icon name="star" size="14" /> <b>{{ number_format($hotel->rating_avg, 1) }}</b> · {{ $hotel->rating_count }} yorum</li>@endif
        <li><x-ui-icon name="paw" size="14" /> <b>{{ $hotel->rooms->count() }}</b> oda tipi</li>
      </ul>
    </div>

    @if($am)
    <div class="apt-seller">
      <div class="apt-seller__head">
        <span class="apt-seller__avatar"><x-ui-icon name="sparkles" size="20" /></span>
        <div>
          <p class="apt-seller__name">Öne çıkan olanaklar</p>
          <p class="apt-seller__sub">Konaklama sırasında sunulanlar</p>
        </div>
      </div>
      <div class="apt-chips">
        @foreach(array_slice($am, 0, 8) as $a)<span class="apt-chip apt-chip--soft">{{ $a }}</span>@endforeach
      </div>
      @if(count($am) > 8)
        <a class="apt-btn apt-btn--ghost apt-btn--full" style="margin-top:12px" href="#otel-olanaklar">Tümünü gör ({{ count($am) }})</a>
      @endif
    </div>
    @endif

  </aside>
</div>

@if($onlineBookingEnabled)
<aside class="booking-modal" id="rezervasyon-talebi" role="dialog" aria-modal="true" aria-labelledby="booking-dialog-title" aria-hidden="true" hidden>
  <div class="booking-modal-panel">
    <header class="booking-modal-header"><div class="booking-modal-hotel-photo">@if($primaryPhotoUrl)<img src="{{ $primaryPhotoUrl }}" alt="{{ $ad }} tesis görseli" width="88" height="68">@else<span><x-ui-icon name="building" size="24" /></span>@endif</div><div class="booking-modal-hotel-title"><span class="booking-modal-kicker">{{ $hotel->license_verified_at?'✓ RUHSATI DOĞRULANDI':'ANTALYA PET OTEL' }} · {{ $ilce }}</span><h2 id="booking-dialog-title">{{ $ad }}</h2><small><x-ui-icon name="pin" size="14" /> {{ $ilce }}, {{ $sehir }}</small></div><button type="button" class="booking-modal-close" data-booking-close aria-label="Pencereyi kapat">×</button></header>
    <div class="booking-modal-tabs" role="tablist"><button type="button" role="tab" aria-selected="true" class="is-active" data-booking-tab="form"><x-ui-icon name="clock" size="18" /> <span>Online rezervasyon</span></button><button type="button" role="tab" aria-selected="false" data-booking-tab="details"><x-ui-icon name="home" size="18" /> <span>Tesis detayları & hizmetler</span></button></div>
    <div class="booking-modal-body"><section class="booking-pane" data-booking-pane="form">

<section class="booking-request-intro" aria-label="Çevrimiçi rezervasyon bilgisi"><span class="booking-request-icon"><x-ui-icon name="book" size="22" /></span><div><span class="booking-request-eyebrow">ÇEVRİMİÇİ REZERVASYON</span><h3>{{ $ad }} için konaklamanızı planlayın</h3><p>Oda, dostunuz ve tarihleri seçerek talep oluşturun. Talep platforma kaydedilir; e-posta iletimi doğrulanamazsa uyarı gösterilir. Rezervasyon işletme onayı olmadan kesinleşmez.</p></div></section><div class="booking-trust-row"><span><x-ui-icon name="check" size="15" /> Online talep</span><span><x-ui-icon name="building" size="15" /> Ödeme tesiste</span><span><x-ui-icon name="clock" size="15" /> Tesis onayı gerekir</span></div><div class="booking-form-section-heading"><span><x-ui-icon name="paw" size="18" /></span><div><b>Konaklama bilgileri</b><small>Dostunuzu, oda tipini ve tarihleri seçin</small></div></div>
@if($indirimler->isNotEmpty())<p class="hint" style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:10px;padding:8px 10px;margin:0 0 12px">&#127991; @foreach($indirimler as $dc){{ $dc->min_nights }}+ gece %{{ $dc->percent }} indirim @if(!$loop->last)· @endif @endforeach</p>@endif

@guest
<div class="booking-whatsapp-dates"><b>Konaklama tarihlerinizi belirleyin</b><small>Giriş ve çıkış tarihinizi seçin; uygun oda seçeneklerini birlikte inceleyin.</small><div><label for="booking-check-in">Giriş<input type="date" id="booking-check-in" value="{{ request('giris', today()->toDateString()) }}" min="{{ today()->toDateString() }}"></label><label for="booking-check-out">Çıkış<input type="date" id="booking-check-out" value="{{ request('cikis', today()->addDay()->toDateString()) }}" min="{{ today()->addDay()->toDateString() }}"></label></div></div>
<p class="sub" style="margin:6px 0 14px">Çevrimiçi rezervasyonunuzu tamamlamak için giriş yapın veya ücretsiz hesap oluşturun. Telefon ve WhatsApp bilgilerine bu sayfadan doğrudan erişebilirsiniz.</p>
<a class="btn" href="{{ route('login') }}">Giriş yap</a> <a class="btn ghost" href="{{ route('register') }}">Kayıt ol</a>
@else
@if($pets->isEmpty())
  <p class="sub" style="margin:6px 0 14px">Önce dostunuzun profilini oluşturun.</p>
  <a class="btn" href="{{ route('pets.create') }}">Profil oluştur</a>
@elseif($hotel->rooms->isEmpty())
  <p class="sub">Bu tesis için oda listesi henüz tanımlı değil.</p>
@else
<form class="form" style="border:0;padding:0;margin:0" method="post" action="{{ route('reservations.store', $hotel->slug) }}">@csrf
  <label><span class="booking-field-icon"><x-ui-icon name="home" size="16" /></span> Oda tipi</label>
  <select name="room_type_id" id="booking-room-type" required><option value="" disabled hidden>Bu dost için uygun oda yok</option>@foreach($hotel->rooms as $room)
    <option value="{{ $room->id }}" data-pet-type="{{ $room->pet_type }}" data-size="{{ $room->size }}" @selected(old('room_type_id', request('oda')) == $room->id)>
      {{ $room->name }} ({{ \App\Models\RoomType::PET_TYPES[$room->pet_type] ?? 'Evcil' }}) - {{ number_format((float) $room->base_price, 0, ',', '.') }} ₺
    </option>
  @endforeach</select>
  <label>Dostunuz</label>
  <select name="pet_id" id="booking-pet" required>@foreach($pets as $p)
    <option value="{{ $p->id }}" data-pet-type="{{ $p->species }}" data-size="{{ $p->size }}" @selected(old('pet_id') == $p->id)>{{ $p->name }} ({{ $p->species_label }}, {{ $p->size_label }})</option>
  @endforeach</select>
  <p id="booking-room-hint" class="hint" role="status" aria-live="polite" style="margin:5px 0 12px"></p>
  <script>
  (function(){
    var rooms=document.getElementById('booking-room-type'),pets=document.getElementById('booking-pet'),hint=document.getElementById('booking-room-hint');
    if(!rooms||!pets)return;
    function syncRooms(){
      var pet=pets.options[pets.selectedIndex],species=pet&&pet.dataset.petType,size=pet&&pet.dataset.size,current=rooms.value,first=null,keepsCurrent=false,placeholder=rooms.options[0];
      Array.prototype.forEach.call(rooms.options,function(option){
        if(!option.value)return;
        var matches=option.dataset.petType===species&&(species!=='dog'||option.dataset.size==='any'||option.dataset.size===size);
        option.hidden=false;option.disabled=!matches;
        if(matches&&!first)first=option;if(matches&&option.value===current)keepsCurrent=true;
      });
      if(first){placeholder.hidden=true;placeholder.disabled=true;if(!keepsCurrent)rooms.value=first.value;rooms.disabled=false;hint.textContent='';}
      else{placeholder.hidden=false;placeholder.disabled=false;rooms.value='';rooms.disabled=false;hint.textContent='Bu dostunuz için uygun oda görünmüyor. Başka bir dost profili seçebilir veya tesisle doğrudan iletişime geçebilirsiniz.';}
      rooms.dispatchEvent(new Event('change',{bubbles:true}));
    }
    pets.addEventListener('change',syncRooms);syncRooms();
  })();
  </script>
  <div class="row2"><div><label>Giriş</label><input type="date" id="booking-check-in" name="check_in" value="{{ old('check_in', request('giris', today()->toDateString())) }}" min="{{ today()->toDateString() }}" required></div>
  <div><label>Çıkış</label><input type="date" id="booking-check-out" name="check_out" value="{{ old('check_out', request('cikis')) }}" min="{{ today()->addDay()->toDateString() }}" required></div></div>
  <script>
  (function(){var start=document.getElementById('booking-check-in'),end=document.getElementById('booking-check-out');if(!start||!end)return;function sync(){var now=new Date(),fallback=now.getFullYear()+'-'+String(now.getMonth()+1).padStart(2,'0')+'-'+String(now.getDate()).padStart(2,'0'),d=new Date((start.value||fallback)+'T00:00:00');d.setDate(d.getDate()+1);var min=d.getFullYear()+'-'+String(d.getMonth()+1).padStart(2,'0')+'-'+String(d.getDate()).padStart(2,'0');end.min=min;if(!end.value||end.value<min)end.value=min}start.addEventListener('click',function(){if(start.showPicker)try{start.showPicker()}catch(e){}});end.addEventListener('click',function(){if(end.showPicker)try{end.showPicker()}catch(e){}});start.addEventListener('change',sync);sync()})();
  </script>
  @if($hotel->transfer_enabled)
  <fieldset class="transfer-box"><legend>Pet Taxi · Transfer</legend>
  <p class="hint">@if($hotel->transfer_mode === 'free')Bu tesis ücretsiz transfer sunuyor.@else Ücret, ücretsiz OpenStreetMap rota servisiyle hesaplanır: {{ number_format((float)config('booking.transfer_base_fee',150),2,',','.') }} ₺ başlangıç + {{ number_format((float)config('booking.transfer_per_km',15),2,',','.') }} ₺/km. Gidiş dönüşte iki yol mesafesi hesaplanır. Rota bulunamazsa tesisin sabit bölge tarifesi kullanılır. Adres, ücret teklifi için OpenStreetMap servislerine gönderilir.@endif {{ $hotel->transfer_instructions }}</p>
  <label class="chk"><input id="transfer-check" type="checkbox" name="transfer_requested" value="1" @checked(old('transfer_requested'))> Dostum için pet taksi istiyorum</label>
  <div id="transfer-form" data-paid="{{ $hotel->transfer_mode === 'paid' ? '1' : '0' }}" hidden>
  <label>Transfer yönü</label><select name="transfer_type" id="transfer-type"><option value="one_way_pickup">Adresten tesise alınsın</option><option value="one_way_dropoff">Tesisten adrese bırakılsın</option><option value="round_trip">Gidiş dönüş</option></select>
  <label>Adres <small>(ilçe, mahalle ve sokak bilgisini ekleyin)</small></label><input name="transfer_pickup_address" id="transfer-pickup-address" value="{{ old('transfer_pickup_address') }}" maxlength="500" placeholder="Örn. Muratpaşa, Lara Mahallesi, Antalya" required>
  <div id="transfer-return" hidden><label>Dönüş adresi</label><input name="transfer_return_address" id="transfer-return-address" value="{{ old('transfer_return_address') }}" maxlength="500" placeholder="Örn. Muratpaşa, Antalya"><label>Dönüş tarihi ve saati</label><input type="datetime-local" name="transfer_return_at" min="{{ now()->format('Y-m-d\TH:i') }}" value="{{ old('transfer_return_at') }}"></div>
  <label>Alınma / bırakılma tarihi ve saati</label><input type="datetime-local" name="transfer_pickup_at" min="{{ now()->format('Y-m-d\TH:i') }}" value="{{ old('transfer_pickup_at') }}" required>
  <label>Araç tipi</label><select name="transfer_vehicle_type" required><option value="sedan">Standart araç</option><option value="minivan">Minivan</option><option value="van">Geniş araç</option></select>
  <button type="button" id="transfer-calculate" class="btn ghost sm">Mesafeyi ve ücreti hesapla</button>
  <p id="transfer-fee-result" class="hint" role="status" aria-live="polite">Adresleri girip ücreti hesaplayın. Ödeme adımında bu ücret toplam tutara eklenir.</p>
  <label class="chk"><input type="checkbox" name="transfer_fee_accepted" id="transfer-fee-accepted" value="1" required disabled> Hesaplanan transfer ücretini ve toplam fiyat güncellemesini kabul ediyorum.</label><input type="hidden" name="transfer_quote_token" id="transfer-quote-token" value=""><input type="hidden" id="transfer-quote-confirmed" value="0">
  </div></fieldset>
  <script>(function(){var c=document.getElementById('transfer-check'),f=document.getElementById('transfer-form'),type=document.getElementById('transfer-type'),ret=document.getElementById('transfer-return'),btn=document.getElementById('transfer-calculate'),result=document.getElementById('transfer-fee-result'),confirmed=document.getElementById('transfer-quote-confirmed'),pickup=document.getElementById('transfer-pickup-address'),returnAddress=document.getElementById('transfer-return-address'),timer;function invalidate(){confirmed.value='0';document.getElementById('transfer-quote-token').value='';var accepted=document.getElementById('transfer-fee-accepted');if(accepted){accepted.checked=false;accepted.disabled=true}result.textContent='Bilgiler değişti. Yeni ücreti hesaplayın.'}function sync(){var active=c.checked,round=active&&type.value==='round_trip';f.hidden=!active;ret.hidden=!round;var accepted=document.getElementById('transfer-fee-accepted');accepted.required=active;accepted.disabled=!active;var a=f.querySelector('[name="transfer_pickup_address"]'),b=f.querySelector('[name="transfer_pickup_at"]'),v=f.querySelector('[name="transfer_vehicle_type"]'),r=f.querySelector('[name="transfer_return_address"]'),rt=f.querySelector('[name="transfer_return_at"]');a.required=active;b.required=active;v.required=active;r.required=round;rt.required=round;if(active)invalidate()}c.addEventListener('change',sync);type.addEventListener('change',sync);pickup.addEventListener('input',invalidate);if(returnAddress)returnAddress.addEventListener('input',invalidate);sync();btn.addEventListener('click',function(){clearTimeout(timer);result.textContent='Ücret hesaplanıyor…';btn.disabled=true;fetch('{{ route('transfer.calculate-fee') }}',{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json','X-Requested-With':'XMLHttpRequest'},body:JSON.stringify({hotel_slug:@json($hotel->slug),transfer_type:type.value,pickup_address:pickup.value,return_address:returnAddress?returnAddress.value:'',pickup_at:f.querySelector('[name="transfer_pickup_at"]').value,return_at:f.querySelector('[name="transfer_return_at"]')?f.querySelector('[name="transfer_return_at"]').value:'',vehicle_type:v.value,_token:'{{ csrf_token() }}'})}).then(function(r){return r.json()}).then(function(d){btn.disabled=false;if(!d.ok){result.textContent=d.message||'Ücret hesaplanamadı.';return}var fee=Number(d.fee||0);result.textContent='Hesaplanan transfer ücreti: '+fee.toLocaleString('tr-TR',{minimumFractionDigits:2,maximumFractionDigits:2})+' ₺ ('+Number(d.distance_km||0).toLocaleString('tr-TR',{maximumFractionDigits:1})+' km)';document.getElementById('transfer-quote-token').value=d.quote_token||'';confirmed.value='1';var accepted=document.getElementById('transfer-fee-accepted');accepted.disabled=false;accepted.checked=true;f.dispatchEvent(new Event('change',{bubbles:true}))}).catch(function(){btn.disabled=false;result.textContent='Ücret hesaplanamadı. Lütfen tekrar deneyin.'})})})();
  </script>
  @endif
  @if(($groomingOptions ?? collect())->isNotEmpty())
  <fieldset class="transfer-box"><legend>Kuaför ve yıkama</legend>
  <label>İsteğe bağlı hizmet</label><select name="grooming_option_id" id="grooming-option"><option value="">Hizmet seçmeyin</option>@foreach($groomingOptions as $groomingOption)<option value="{{ $groomingOption->id }}" @selected(old('grooming_option_id')==$groomingOption->id)>{{ $groomingOption->title }} — {{ number_format((float)$groomingOption->price,2,',','.') }} ₺</option>@endforeach</select></fieldset>
  @endif
  @if(config('booking_addons.insurance_enabled') && (float)config('booking_addons.daily_insurance_rate',0)>0 && config('booking_addons.insurance_provider_name') && config('booking_addons.insurance_terms'))<fieldset class="transfer-box"><legend>Konaklama sigortası</legend><label class="chk"><input type="checkbox" name="has_insurance" value="1" @checked(old('has_insurance'))> Konaklamaya günlük {{ number_format((float)config('booking_addons.daily_insurance_rate'),2,',','.') }} ₺ tutarında sigorta ekle</label><button type="button" class="btn ghost sm" onclick="document.getElementById('insurance-terms').showModal()">Sigorta koşulları</button><dialog id="insurance-terms"><h3>{{ config('booking_addons.insurance_provider_name') }} · Sigorta koşulları</h3><p style="white-space:pre-line">{{ config('booking_addons.insurance_terms') }}</p><button type="button" class="btn" onclick="this.closest('dialog').close()">Kapat</button></dialog></fieldset>@endif
  @if(auth()->check() && (int)auth()->user()->reward_points>0)<fieldset class="transfer-box"><legend>PotiPuan</legend><p class="hint">Bakiyeniz: {{ (int)auth()->user()->reward_points }} puan · 1 puan = 1 ₺ indirim</p><label>Kullanılacak puan</label><input type="number" name="points_to_use" min="0" max="{{ (int)auth()->user()->reward_points }}" step="1" value="{{ old('points_to_use',0) }}"></fieldset>@endif
  @if($extras->isNotEmpty())
  <label>Ek hizmetler <small>(isteğe bağlı)</small></label>
  @foreach($extras as $ex)<label class="chk"><input type="checkbox" name="extras[]" value="{{ $ex->id }}" @checked(in_array($ex->id, array_map('intval', (array) old('extras', [])), true))> <span>{{ $ex->name }} — {{ number_format((float) $ex->price, 0, ',', '.') }} ₺ <small>({{ $ex->unit_label }})</small></span></label>@endforeach
  @endif
  <label>Not</label>
  <textarea name="customer_note" style="min-height:70px" placeholder="Varsa aşı, beslenme veya oda tercihinizi yazın.">{{ old('customer_note') }}</textarea>
  <aside class="booking-cancellation-summary" aria-label="İptal ve iade özeti">
    <strong><span aria-hidden="true">↩</span> İptal ve iade özeti</strong>
    <ul>
      <li>Rezervasyon çevrimiçi alınır; tesis müsaitliği onaylayana kadar kesinleşmez.</li>
      <li>Giriş tarihinden önce, rezervasyon ayrıntılarınızdan talep veya onaylanmış rezervasyonu iptal edebilirsiniz.</li>
      <li>Ödeme rezervasyon onayında değil, tesiste yapılır. Varsa tesise yapılmış ön ödeme veya ücret iadesinde tesisin koşulları geçerlidir; ayrıntıları <a href="{{ route('pages.show', 'iptal-iade') }}" target="_blank" rel="noopener">İptal ve İade koşullarında</a> inceleyin.</li>
    </ul>
  </aside>
  <label class="chk"><input type="checkbox" name="terms" value="1" required>
    <span><a href="{{ route('pages.show', 'iptal-iade') }}" target="_blank" rel="noopener"><u>İptal ve iade koşullarını</u></a> okudum, kabul ediyorum.</span></label>
  <label class="chk"><input type="checkbox" name="whatsapp_consent" value="1" @checked(old('whatsapp_consent'))>
    <span>Rezervasyon talebi ve durumu hakkında WhatsApp bilgilendirmesi almayı kabul ediyorum. Bu izin isteğe bağlıdır.</span></label>
  <div id="teklif" class="hint" aria-live="polite" style="margin:4px 0 10px"></div>
  <button class="btn" style="width:100%">Çevrimiçi rezervasyon gönder</button>
</form>
<script>
(function(){
  var f=document.querySelector('form[action="{{ route('reservations.store', $hotel->slug) }}"]');
  var box=document.getElementById('teklif');
  if(!f||!box||!window.fetch||!window.URLSearchParams)return;
  var t;
  function fmt(n){return Number(n).toLocaleString('tr-TR')+' ₺';}
  function run(){
    var fd=new FormData(f), q=new URLSearchParams();
    ['room_type_id','check_in','check_out','transfer_type','transfer_pickup_address','transfer_return_address','grooming_option_id','has_insurance','points_to_use'].forEach(function(k){q.set(k,fd.get(k)||'');});
    q.set('transfer_requested',document.getElementById('transfer-quote-confirmed')&&document.getElementById('transfer-quote-confirmed').value==='1'?(fd.get('transfer_requested')||''):'');
    fd.getAll('extras[]').forEach(function(v){q.append('extras[]',v);});
    if(!q.get('check_in')||!q.get('check_out')){box.textContent='';return;}
    fetch('{{ route('reservations.quote', $hotel->slug) }}?'+q.toString(),{headers:{'Accept':'application/json'}})
      .then(function(r){return r.json();})
      .then(function(d){
        box.textContent='';
        if(!d.ok){box.textContent=d.message||'';return;}
        var b=document.createElement('b');b.textContent='Tahmini toplam: '+fmt(d.total)+' ('+d.nights+' gece)';box.appendChild(b);
        function satir(m,renk){var s=document.createElement('div');s.textContent=m;if(renk)s.style.color=renk;box.appendChild(s);}
        if(d.discount_total>0)satir('Kampanya indirimi: −'+fmt(d.discount_total)+' (%'+d.discount_percent+')');
        satir(d.vip_tier+' Sadakat seviyesi');
        if(d.vip_discount_total>0)satir('VIP indirimi: −'+fmt(d.vip_discount_total)+' (otel '+fmt(d.vip_hotel_discount_total)+' · platform komisyon katkısı '+fmt(d.vip_platform_discount_total)+')','#146c43');
        if(d.extras_total>0)satir('Ek hizmetler: '+fmt(d.extras_total));
        if(d.transfer_total>0)satir('Pet transfer: '+fmt(d.transfer_total));
        if(d.grooming_fee>0)satir('Kuaför: '+fmt(d.grooming_fee));
        if(d.insurance_fee>0)satir('Sigorta: '+fmt(d.insurance_fee));
        if(d.points_discount>0)satir('PotiPuan indirimi: −'+fmt(d.points_discount));
        if(!d.available)satir('Bu tarihlerde seçili oda dolu görünüyor.','#b42318');
      }).catch(function(){});
  }
  f.addEventListener('change',function(){clearTimeout(t);t=setTimeout(run,250);});
  run();
})();
</script>
@endif
@endguest
@if($hotel->phone)<p class="hotel-contact-links"><a data-hotel-phone-call href="tel:{{ preg_replace('/[^0-9+]/', '', $hotel->phone) }}">☎ {{ $hotel->phone }}</a><a class="hotel-whatsapp-link" href="https://wa.me/{{ \App\Support\PhoneNumber::whatsapp($hotel->phone) }}?text={{ rawurlencode('Merhaba '.$hotel->name.', tesisiniz hakkında bilgi almak istiyorum.') }}" target="_blank" rel="noopener noreferrer">WhatsApp ile yazın</a></p>@endif
</section><section class="booking-pane booking-details-pane" data-booking-pane="details" hidden>
<div class="booking-details-photo-grid" aria-label="{{ $ad }} tesis fotoğrafları">@if($primaryPhotoUrl)<img class="booking-details-photo-main" src="{{ $primaryPhotoUrl }}" alt="{{ $ad }} tesis görünümü{{ $temsiliKapak ? ', temsili görsel; tesis fotoğrafı paylaşılmadı' : '' }}" loading="lazy">@endif @foreach($bookingGallery as $bookingPhoto)<img src="{{ $bookingPhoto }}" alt="{{ $ad }} tesis görseli" loading="lazy">@endforeach</div>@if($temsiliKapak)<p class="hint">Kapak görseli temsili görünümdür; işletme tesis fotoğrafı paylaşmamıştır.</p>@endif<div class="booking-info-banner"><span class="booking-info-banner-icon"><x-ui-icon name="message" size="19" /></span><div><b>{{ $ad }} çevrimiçi rezervasyon</b><p>Talep platforma kaydedilir; e-posta iletimi doğrulanamazsa uyarı gösterilir. Rezervasyon işletme onayı olmadan kesinleşmez.</p></div></div>
<h3>Tesis hakkında</h3><p>{{ \Illuminate\Support\Str::limit(strip_tags((string)$hotel->description),900) ?: $ad.' · '.$ilce.', Antalya’da hizmet vermektedir.' }}</p>
<h3>Oda seçenekleri</h3><div class="booking-room-list">
@foreach($hotel->rooms as $room)
<div><span>{{ $room->name }} <small>· {{ \App\Models\RoomType::PET_TYPES[$room->pet_type]??'Evcil hayvan' }}</small></span><b>{{ number_format((float)$room->base_price,0,',','.') }} ₺ <small>/gece</small></b></div>
@endforeach
</div>
<h3>Hizmetler ve olanaklar</h3><div class="booking-amenities">{{ implode(' · ',array_map('strval',$am)) }}</div>
<h3>Konaklama kuralları</h3><p class="booking-policy">{{ $hotel->policies }}</p>
@if($hotel->phone)<p class="hotel-contact-links"><a data-hotel-phone-call href="tel:{{ preg_replace('/[^0-9+]/', '', $hotel->phone) }}">☎ {{ $hotel->phone }}</a> <a class="hotel-whatsapp-link" href="https://wa.me/{{ \App\Support\PhoneNumber::whatsapp($hotel->phone) }}?text={{ rawurlencode('Merhaba '.$hotel->name.', tesisiniz hakkında bilgi almak istiyorum.') }}" target="_blank" rel="noopener noreferrer">WhatsApp ile yazın</a></p>@endif
</section></div>
  </div>
</aside>
@endif
<script>
(function(){
  var modal=document.getElementById('rezervasyon-talebi');if(!modal)return;
  var previous=null,requestedRoomId='';  var dateStart=document.getElementById('booking-check-in'),dateEnd=document.getElementById('booking-check-out');
  if(!document.getElementById('booking-room-type')&&dateStart&&dateEnd){function syncGuestDates(){if(!dateStart.value)return;var d=new Date(dateStart.value+'T00:00:00');d.setDate(d.getDate()+1);var min=d.getFullYear()+'-'+String(d.getMonth()+1).padStart(2,'0')+'-'+String(d.getDate()).padStart(2,'0');dateEnd.min=min;if(!dateEnd.value||dateEnd.value<min)dateEnd.value=min}dateStart.addEventListener('change',syncGuestDates);syncGuestDates()}
  function close(){modal.hidden=true;modal.setAttribute('aria-hidden','true');document.body.classList.remove('booking-modal-open');if(previous)previous.focus()}
  function open(){previous=document.activeElement;modal.hidden=false;modal.setAttribute('aria-hidden','false');document.body.classList.add('booking-modal-open');modal.querySelector('[data-booking-close]').focus()}
  document.querySelectorAll('[data-booking-open]').forEach(function(b){b.addEventListener('click',function(){var roomId=b.dataset.bookingRoomId,select=document.getElementById('booking-room-type');if(roomId){requestedRoomId=roomId}if(roomId&&select){select.value=roomId;select.dispatchEvent(new Event('change',{bubbles:true}))}else if(select&&select.value){requestedRoomId=select.value}open()})});
  modal.querySelector('[data-booking-close]').addEventListener('click',close);modal.addEventListener('click',function(e){if(e.target===modal)close()});document.addEventListener('keydown',function(e){if(e.key==='Escape'&&!modal.hidden)close()});
  modal.querySelectorAll('[data-booking-tab]').forEach(function(tab){tab.addEventListener('click',function(){var selected=tab.dataset.bookingTab;modal.querySelectorAll('[data-booking-tab]').forEach(function(t){var on=t===tab;t.classList.toggle('is-active',on);t.setAttribute('aria-selected',on?'true':'false')});modal.querySelectorAll('[data-booking-pane]').forEach(function(p){p.hidden=p.dataset.bookingPane!==selected})})});
  function roomLink(id){if(!id)return null;return document.querySelector('[data-room-whatsapp][data-room-id="'+String(id).replace(/[^0-9]/g,'')+'"]')}
  document.querySelectorAll('a.hotel-whatsapp-link,a.otel-whatsapp-action,a[data-room-whatsapp]').forEach(function(a){a.addEventListener('click',function(){
    var params=new URLSearchParams(window.location.search),roomSelect=document.getElementById('booking-room-type'),petSelect=document.getElementById('booking-pet');
    var roomId=a.dataset.roomId||(roomSelect&&roomSelect.value)||requestedRoomId||params.get('oda')||'',room=roomLink(roomId),roomName=room&&room.dataset.roomName||'',petType=room&&room.dataset.petType||'',roomSize=room&&room.dataset.roomSize||'',price=room&&room.dataset.roomPrice||'';
    if(roomSelect&&!a.dataset.roomId&&roomSelect.selectedOptions.length){var selectedRoom=roomSelect.selectedOptions[0];roomName=selectedRoom.textContent.trim().replace(/\s*\([^)]*\)\s*-.*$/,'');petType=selectedRoom.dataset.petType||petType;roomSize=selectedRoom.dataset.size||roomSize}
    var checkIn=document.getElementById('booking-check-in'),checkOut=document.getElementById('booking-check-out'),start=checkIn&&checkIn.value||params.get('giris')||'',end=checkOut&&checkOut.value||params.get('cikis')||'';
    var pet=petSelect&&petSelect.selectedOptions[0],hotelName=@json($hotel->name),parts=['Merhaba, '+hotelName+' için konaklama bilgisi rica ediyorum.'];
    if(roomName)parts.push('Oda: '+roomName+(petType?' ('+petType+(roomSize?' · '+roomSize:'')+')':'' )+(price?' · '+Number(price).toLocaleString('tr-TR')+' ₺/gece':''));
    else parts.push('Uygun oda seçenekleri hakkında bilgi rica ediyorum.');
    if(pet&&pet.textContent)parts.push('Dostum: '+pet.textContent.trim());
    function formatDate(value){return value?value.split('-').reverse().join('.'):''}if(start)parts.push('Giriş: '+formatDate(start));if(end)parts.push('Çıkış: '+formatDate(end));if(!start&&!end)parts.push('Konaklama tarihlerini birlikte netleştirebilir miyiz?');
    parts.push('Bu tarihlerde müsaitlik ve güncel fiyatı teyit edebilir misiniz?');
    var target=new URL(a.href);target.searchParams.set('text',parts.join('\n'));a.href=target.toString();
  })})
})();
</script>
<dialog class="otel-lightbox" id="otelLightbox" aria-label="Tesis görselleri">
  <button type="button" class="otel-lightbox-close" aria-label="Görseli kapat">×</button>
  <button type="button" class="otel-lightbox-prev" aria-label="Önceki görsel">‹</button>
  <figure><img id="otelLightboxImage" alt="" src="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7"><figcaption id="otelLightboxCaption"></figcaption></figure>
  <button type="button" class="otel-lightbox-next" aria-label="Sonraki görsel">›</button>
</dialog>
<script>
(function(){
  var dialog=document.getElementById('otelLightbox'), large=document.getElementById('otelLightboxImage');
  var caption=document.getElementById('otelLightboxCaption');
  if(!dialog||!large||typeof dialog.showModal!=='function')return;
  var images=Array.from(document.querySelectorAll('.apt-gallery img,.gal-izgara img'));
  if(!images.length)return;
  var current=0;
  function show(i){current=(i+images.length)%images.length;var source=images[current];large.src=source.currentSrc||source.src;large.alt=source.alt||'Tesis görseli';caption.textContent=(current+1)+' / '+images.length+' · '+large.alt;}
  function open(i){show(i);dialog.showModal();dialog.querySelector('.otel-lightbox-close').focus();}
  images.forEach(function(image,i){image.classList.add('otel-zoomable');image.tabIndex=0;image.setAttribute('role','button');image.setAttribute('aria-label',(image.alt||'Tesis görseli')+' görselini büyüt');image.addEventListener('click',function(){open(i);});image.addEventListener('keydown',function(e){if(e.key==='Enter'||e.key===' '){e.preventDefault();open(i);}});});
  document.querySelectorAll('[data-gallery-open]').forEach(function(b){b.addEventListener('click',function(e){if(e.target.closest('img')&&!b.classList.contains('apt-gallery__count'))return;e.preventDefault();open(0);});});
  dialog.querySelector('.otel-lightbox-close').addEventListener('click',function(){dialog.close();});
  dialog.querySelector('.otel-lightbox-prev').addEventListener('click',function(){show(current-1);});
  dialog.querySelector('.otel-lightbox-next').addEventListener('click',function(){show(current+1);});
  dialog.addEventListener('click',function(e){if(e.target===dialog)dialog.close();});
  dialog.addEventListener('keydown',function(e){if(e.key==='ArrowLeft'){e.preventDefault();show(current-1);}if(e.key==='ArrowRight'){e.preventDefault();show(current+1);}});
})();
</script>
</div>

<div class="apt-sticky-actions">
  @if($hotel->phone)
    <a href="tel:{{ preg_replace('/[^0-9+]/', '', $hotel->phone) }}"
       class="apt-sticky-btn apt-sticky-btn--call"
       aria-label="{{$hotel->phone}} numarasını ara">
      <span aria-hidden="true">☎</span> Ara
    </a>
  @endif
  @if($hotel->phone)
    <a href="https://wa.me/{{ \App\Support\PhoneNumber::whatsapp($hotel->phone) }}?text={{ rawurlencode('Merhaba ' . $hotel->name . ' için konaklama bilgi almak istiyorum.') }}"
       class="apt-sticky-btn apt-sticky-btn--wa"
       target="_blank" rel="noopener noreferrer"
       aria-label="WhatsApp'ta {{$hotel->name}} ile sohbet aç">
      <span aria-hidden="true">✉</span> WhatsApp
    </a>
  @endif
</div>
<style>
.apt-sticky-actions{position:fixed;inset:auto 0 0 0;left:0;right:0;bottom:0;display:flex;gap:8px;background:#fff;border-top:1px solid #e5e9e4;padding:10px 12px;box-shadow:0 -4px 20px rgba(24,35,28,.12);z-index:1000}
.apt-sticky-btn{flex:1;display:flex;align-items:center;justify-content:center;gap:8px;height:48px;border:1px solid #e5e9e4;border-radius:12px;background:#fff;color:#28382e;font-weight:800;font-size:14px;text-decoration:none;text-align:center}
.apt-sticky-btn--wa{border-color:#25d366;background:#25d366;color:#fff}
.apt-sticky-btn--wa:hover{background:#1eb055}
.apt-sticky-btn--call:hover{background:#f7f9f7}
@media(min-width:901px){.apt-sticky-actions{display:none}}
</style>


@endsection
