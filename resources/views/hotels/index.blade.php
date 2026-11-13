@php
    $etiket = $type === 'kedi-otelleri' ? 'Kedi Otelleri'
            : ($type === 'kopek-otelleri' ? 'Köpek Otelleri' : 'Pet Otelleri');
    $adet   = $hotels->total();

    $fiyatFiltresiAktif = preg_match('~^\d{1,5}(?:\.\d{1,2})?-\d{1,5}(?:\.\d{1,2})?$~', (string) request('fiyat', '')) === 1
        || request()->filled('min_fiyat') || request()->filled('max_fiyat');
    $fiyatlar = [];
    foreach ($hotels as $x) {
        if ($fiyatFiltresiAktif || !empty(request('size')) || !empty(request('kapasite')) || $type !== '') {
            if ((float) $x->rooms_min_base_price > 0) $fiyatlar[] = (float) $x->rooms_min_base_price;
            continue;
        }
        foreach ($x->rooms as $o) {
            if ((float) $o->base_price > 0) $fiyatlar[] = (float) $o->base_price;
        }
    }
    $enUcuz   = $fiyatlar ? min($fiyatlar) : null;
    $enPahali = $fiyatlar ? max($fiyatlar) : null;

    $konu = $city->name . ' ' . $etiket;
    $siteBrand = \App\Models\Setting::al('site_name', config('app.name', 'Pet Otel'));

    $giris = $city->name . '’da evcil hayvan konaklaması sunan tesisleri oda tipi, günlük ücret, giriş-çıkış '
        . 'saatleri ve kabul edilen hayvan türlerine göre karşılaştırabilirsiniz. '
        . ($adet > 0 ? $konu . ' kategorisinde şu anda ' . $adet . ' tesis listeleniyor. ' : '')
        . ($enUcuz !== null
            ? ($fiyatFiltresiAktif
                ? 'Seçilen aralıktaki tesislerin gecelik başlangıç ücretleri '
                : 'Günlük oda fiyatları ')
              . number_format($enUcuz, 0, ',', '.') . ' TL ile '
              . number_format($enPahali, 0, ',', '.') . ' TL arasında değişiyor. '
            : '')
        . 'Aşağıdaki her tesis kartında oda tipi ve başlangıç fiyatı birlikte görünür; detay '
        . 'sayfasında tüm odalar, konaklama kuralları ve sıkça sorulan sorular yer alır.';

    $ogeler = [];
    foreach ($hotels as $h) $ogeler[] = ['url' => route('hotels.show', $h->slug), 'ad' => $h->name];
@endphp
@extends('layouts.app')
@section('title', $konu . ' | Fiyat ve Karşılaştırma | ' . $siteBrand)
@section('desc', seo_metin($giris, 158))
@section('ogtitle', $konu)
@section('ogdesc', seo_metin($giris, 200))

@php
    /* Filtre, siralama veya sayfa parametresi varsa bu adres
     * KENDI KENDINI tekrarlayan bir varyanttir. Indexlenmemeli.
     * noindex,follow: Google linkleri takip eder, sayfayi depoda
     * tutmaz. Temiz adres (canonical) ise indexlenir. */
    $filtreVar = request()->hasAny(['fiyat', 'min_fiyat', 'max_fiyat', 'ilce', 'giris', 'cikis', 'page', 'q', 'sirala', 'puan', 'size', 'kapasite', 'olanak']) || request()->routeIs('search');
    $temizUrl = match ($type) {
        'kedi-otelleri' => route('hotels.cats'),
        'kopek-otelleri' => route('hotels.dogs'),
        default => route('hotels.all'),
    };
@endphp
@section('robots', $filtreVar ? 'noindex, follow' : 'index, follow, max-image-preview:large, max-snippet:-1')
@section('canonical', $filtreVar ? $temizUrl : url()->current())
@push('head')
{!! seo_liste_sema($konu, url()->current(), $giris, $ogeler) !!}
{!! sema_kirinti([
    ['ad' => 'Ana Sayfa', 'url' => route('home')],
    ['ad' => $city->name . ' Pet Otelleri', 'url' => route('hotels.all')],
    ['ad' => $konu],
]) !!}
{!! sema_sss($kategoriSss) !!}
@endpush
@section('content')
<div class="wrap apt-list">

  <aside class="apt-list__side" aria-label="Kategori ve filtreler">

    <div class="apt-panel">
      <p class="apt-panel__title">Kategoriler</p>
      <ul class="apt-tree">
        <li><a class="{{ !$type ? 'is-active' : '' }}" href="{{ route('hotels.all') }}"><span>Tüm pet otelleri</span><span class="apt-tree__count">{{ $adet }}</span></a></li>
        <li><a class="{{ $type === 'kopek-otelleri' ? 'is-active' : '' }}" href="{{ route('hotels.dogs') }}"><span>Köpek otelleri</span></a></li>
        <li><a class="{{ $type === 'kedi-otelleri' ? 'is-active' : '' }}" href="{{ route('hotels.cats') }}"><span>Kedi otelleri</span></a></li>
        <li><a href="{{ route('fiyatlar') }}"><span>Fiyat karşılaştırması</span></a></li>
        <li><a href="{{ route('pages.show','nasil-calisir') }}"><span>Nasıl çalışır?</span></a></li>
      </ul>
    </div>

    @if($type !== 'kedi-otelleri')
    <div class="apt-panel">
      <p class="apt-panel__title">Köpek boyutu</p>
      <form class="apt-filter" method="get" action="{{ url()->current() }}">
        @foreach(request()->except(['size', 'page']) as $filterName => $filterValue)
          @if(is_array($filterValue))
            @foreach($filterValue as $nestedValue)<input type="hidden" name="{{ $filterName }}[]" value="{{ $nestedValue }}">@endforeach
          @elseif($filterValue !== null)
            <input type="hidden" name="{{ $filterName }}" value="{{ $filterValue }}">
          @endif
        @endforeach
        <div>
          <label for="listing-dog-size">Kabul edilen evcil hayvan boyutu</label>
          <select id="listing-dog-size" name="size">
            <option value="" @selected(empty($filtre['size']))>Tüm boylar</option>
            <option value="small" @selected(($filtre['size'] ?? '') === 'small')>Küçük ırk</option>
            <option value="medium" @selected(($filtre['size'] ?? '') === 'medium')>Orta ırk</option>
            <option value="large" @selected(($filtre['size'] ?? '') === 'large')>Büyük ırk</option>
          </select>
        </div>
        <button class="apt-btn apt-btn--ghost apt-btn--full" type="submit">Filtreyi uygula</button>
      </form>
    </div>
    @endif

    <div class="apt-panel">
      <p class="apt-panel__title">Gecelik oda fiyatı</p>
      <form class="apt-filter" method="get" action="{{ url()->current() }}">
        @foreach(request()->except(['fiyat', 'page']) as $filterName => $filterValue)
          @if(is_array($filterValue))
            @foreach($filterValue as $nestedValue)<input type="hidden" name="{{ $filterName }}[]" value="{{ $nestedValue }}">@endforeach
          @elseif($filterValue !== null)
            <input type="hidden" name="{{ $filterName }}" value="{{ $filterValue }}">
          @endif
        @endforeach
        <div>
          <label for="listing-price">Fiyat aralığı</label>
          <select id="listing-price" name="fiyat">
            <option value="" @selected(!preg_match('~^\d{1,5}(?:\.\d{1,2})?-\d{1,5}(?:\.\d{1,2})?$~', (string) request('fiyat', '')))>Tüm fiyatlar</option>
            <option value="0-700" @selected(request('fiyat') === '0-700')>700 ₺’ye kadar</option>
            <option value="700.01-1000" @selected(request('fiyat') === '700.01-1000')>700,01–1.000 ₺</option>
            <option value="1000.01-1500" @selected(request('fiyat') === '1000.01-1500')>1.000,01–1.500 ₺</option>
            <option value="1500.01-99999" @selected(request('fiyat') === '1500.01-99999')>1.500 ₺ üzeri</option>
          </select>
        </div>
        <div class="apt-filter__price-bounds">
          <label for="listing-min-price">En düşük (₺)</label>
          <input id="listing-min-price" type="number" name="min_fiyat" min="0" max="999999" step="0.01" value="{{ $filtre['min_fiyat'] ?? request('min_fiyat') }}" inputmode="decimal">
          <label for="listing-max-price">En yüksek (₺)</label>
          <input id="listing-max-price" type="number" name="max_fiyat" min="0" max="999999" step="0.01" value="{{ $filtre['max_fiyat'] ?? request('max_fiyat') }}" inputmode="decimal">
        </div>
        <button class="apt-btn apt-btn--ghost apt-btn--full" type="submit">Filtreyi uygula</button>
      </form>
    </div>

    <div class="apt-panel">
      <p class="apt-panel__title">Ek filtreler</p>
      <form class="apt-filter" method="get" action="{{ url()->current() }}">
        @foreach(request()->except(['kapasite', 'puan', 'olanak', 'page']) as $filterName => $filterValue)
          @if(is_array($filterValue))
            @foreach($filterValue as $nestedValue)<input type="hidden" name="{{ $filterName }}[]" value="{{ $nestedValue }}">@endforeach
          @elseif($filterValue !== null)
            <input type="hidden" name="{{ $filterName }}" value="{{ $filterValue }}">
          @endif
        @endforeach
        @php
          $selectedAmenities = is_array(request('olanak')) ? request('olanak') : [];
        @endphp
        <div>
          <label for="listing-capacity">Oda kapasitesi (en az)</label>
          <select id="listing-capacity" name="kapasite">
            <option value="" @selected(empty($filtre['kapasite']))>Tüm kapasiteler</option>
            @foreach([1, 2, 3, 4] as $capacityOption)
              <option value="{{ $capacityOption }}" @selected((int) ($filtre['kapasite'] ?? 0) === $capacityOption)>{{ $capacityOption }} ve üzeri dost</option>
            @endforeach
          </select>
        </div>
        <div>
          <label for="listing-rating">En düşük puan</label>
          <select id="listing-rating" name="puan">
            <option value="" @selected(empty($filtre['puan']))>Tüm puanlar</option>
            @foreach([3, 4, 4.5] as $ratingOption)
              <option value="{{ $ratingOption }}" @selected((float) ($filtre['puan'] ?? 0) === (float) $ratingOption)>{{ number_format($ratingOption, 1, ',', '.') }} ve üzeri</option>
            @endforeach
          </select>
        </div>
        <fieldset class="apt-filter__fieldset">
          <legend>Olanaklar ve hizmetler</legend>
          <div class="apt-filter__checklist">
            @foreach($olanaklar as $amenityKey => $amenity)
              <label class="apt-filter__check"><input type="checkbox" name="olanak[]" value="{{ $amenityKey }}" @checked(in_array($amenityKey, $selectedAmenities, true))><span>{{ $amenity[0] }}</span></label>
            @endforeach
          </div>
        </fieldset>
        <button class="apt-btn apt-btn--ghost apt-btn--full" type="submit">Filtreleri uygula</button>
      </form>
    </div>

    <div class="apt-panel">
      <p class="apt-panel__title">Sıralama</p>
      <form class="apt-filter" method="get" action="{{ url()->current() }}">
        @foreach(request()->except(['sirala', 'fiyat', 'page']) as $filterName => $filterValue)
          @if(is_array($filterValue))
            @foreach($filterValue as $nestedValue)<input type="hidden" name="{{ $filterName }}[]" value="{{ $nestedValue }}">@endforeach
          @elseif($filterValue !== null)
            <input type="hidden" name="{{ $filterName }}" value="{{ $filterValue }}">
          @endif
        @endforeach
        @if(preg_match('~^\d{1,5}(?:\.\d{1,2})?-\d{1,5}(?:\.\d{1,2})?$~', (string) request('fiyat', '')))
          <input type="hidden" name="fiyat" value="{{ request('fiyat') }}">
        @endif
        <div>
          <label for="listing-sort">Listeleme</label>
          <select id="listing-sort" name="sirala" onchange="this.form.submit()">
            <option value="" @selected(empty($filtre['sirala']))>Önerilen sıralama</option>
            <option value="ucuz" @selected(($filtre['sirala'] ?? '') === 'ucuz')>Önce en ucuz</option>
            <option value="pahali" @selected(($filtre['sirala'] ?? '') === 'pahali')>En yüksek oda fiyatı</option>
            <option value="puan" @selected(($filtre['sirala'] ?? '') === 'puan')>En yüksek puan</option>
            <option value="yorum" @selected(($filtre['sirala'] ?? '') === 'yorum')>En çok yorum alan</option>
            <option value="dogrulanmis" @selected(($filtre['sirala'] ?? '') === 'dogrulanmis')>Önce ruhsatı doğrulananlar</option>
          </select>
        </div>
        <noscript><button class="apt-btn apt-btn--ghost apt-btn--full" type="submit">Uygula</button></noscript>
      </form>
    </div>

    @if($ilceListesi->isNotEmpty())
    <div class="apt-panel">
      <p class="apt-panel__title">{{ $city->name }} ilçeleri</p>
      <ul class="apt-tree">
        @foreach($ilceListesi as $il)
          <li><a href="{{ url('/' . $il->slug) }}"><span>{{ $il->name }}</span></a></li>
        @endforeach
      </ul>
    </div>
    @endif

  </aside>

  <div class="apt-list__main">

    @if($tarihHata)
      <div class="apt-date-error" role="alert">{{ $tarihHata }}</div>
    @endif
    @if($fiyatHata)
      <div class="apt-date-error" role="alert">{{ $fiyatHata }}</div>
    @endif

    <div class="apt-listhead">
      <div>
        <h1>
          @if($filtre['q'])
            "{{ $filtre['q'] }}" için pet otel sonuçları
          @else
            {{ $konu }}
          @endif
        </h1>
        <p class="apt-listhead__count">
          @if($filtre['q'])
            {{ $adet }} tesis aramanızla eşleşti.
          @else
            {{ $adet }} tesis
            @if(!empty($filtre['size']))
              · {{ \App\Models\RoomType::SIZES[$filtre['size']] ?? 'Seçilen boyuttaki' }} ırk köpekler için uygun odalar
            @endif
            @if($enUcuz)
              · {{ number_format($enUcuz, 0, ',', '.') }} TL / geceden
            @endif
          @endif
        </p>
      </div>
      <div class="apt-sort">
        <span>{{ $adet }} sonuç</span>
        <a class="apt-chip" href="{{ route('quote.index') }}">Birden fazla otelden fiyat al</a>
      </div>
    </div>

    <aside class="apt-verification-note" aria-label="Ruhsat doğrulaması hakkında">
      <strong>Ruhsat bilgisi hakkında</strong>
      <p>“Ruhsat doğrulandı” etiketi, tesisin ruhsat bilgisinin platform tarafından kontrol edildiğini belirtir. “Ruhsat kontrol edilmedi”, platformda doğrulama kaydı olmadığı anlamına gelir; tesisin ruhsatsız olduğu anlamına gelmez. Oda fiyatını ve müsaitliği rezervasyon öncesinde işletmeyle teyit edin.</p>
    </aside>

    <div class="apt-chips" style="margin-bottom:14px">
      <a class="apt-chip {{ !$type ? 'is-on' : '' }}" href="{{ route('hotels.all') }}">Tümü</a>
      <a class="apt-chip {{ $type === 'kopek-otelleri' ? 'is-on' : '' }}" href="{{ route('hotels.dogs') }}">Köpek Otelleri</a>
      <a class="apt-chip {{ $type === 'kedi-otelleri' ? 'is-on' : '' }}" href="{{ route('hotels.cats') }}">Kedi Otelleri</a>
      <a class="apt-chip {{ ($filtre['sirala'] ?? '') === 'ucuz' ? 'is-on' : '' }}" href="{{ request()->fullUrlWithQuery(['sirala' => 'ucuz', 'page' => null]) }}">Önce en ucuz</a>
      <a class="apt-chip {{ ($filtre['sirala'] ?? '') === 'pahali' ? 'is-on' : '' }}" href="{{ request()->fullUrlWithQuery(['sirala' => 'pahali', 'page' => null]) }}">En yüksek oda fiyatı</a>
    </div>

    <div class="apt-grid">
      @forelse($hotels as $h)
        @include('hotels._card')
      @empty
        <div class="apt-card">
          <p class="apt-card__body" style="margin:0">Bu kriterlere uygun bir tesis listelenmiyor. Tarihleri veya fiyat aralığını değiştirebilir, filtreleri kaldırabilir ya da <a href="{{ route('hotels.all') }}">tüm tesisleri</a>, <a href="{{ route('hotels.dogs') }}">köpek otellerini</a> veya <a href="{{ route('hotels.cats') }}">kedi otellerini</a> inceleyebilirsiniz.</p>
        </div>
      @endforelse
    </div>

    @if($hotels->hasPages())
      <div style="margin:26px 0">{{ $hotels->links() }}</div>
    @endif

    <section class="apt-card" style="margin-top:22px" aria-labelledby="liste-hakkinda">
      <div class="apt-card__head">
        <span class="apt-card__icon"><x-ui-icon name="paw" size="18" /></span>
        <div>
          <h2 class="apt-card__title" id="liste-hakkinda">{{ $konu }} hakkında</h2>
          <p class="apt-card__sub">{{ $city->name }}’da karşılaştırmalı konaklama rehberi</p>
        </div>
      </div>
      <div class="apt-card__body">{!! makale_guvenli_html('<p>' . e($giris) . '</p>') !!}</div>
    </section>

    <section class="apt-card" style="margin-top:18px" aria-labelledby="liste-cta">
      <div class="apt-card__head">
        <span class="apt-card__icon"><x-ui-icon name="check" size="18" /></span>
        <div>
          <h2 class="apt-card__title" id="liste-cta">Dostunuz için doğru konaklamayı kolayca seçin.</h2>
          <p class="apt-card__sub">Oda tiplerini, hizmetleri ve günlük ücretleri inceleyin. {{ \App\Models\Setting::al('booking_requests_enabled', '0') === '1' ? 'Çevrimiçi talep oluşturmadan önce işletmeyle müsaitliği teyit edin.' : 'Çevrimiçi rezervasyon talepleri şu anda alınmıyor; işletmeyle doğrudan iletişim kurun.' }}</p>
        </div>
      </div>
      <div class="apt-card__body">
        <a class="apt-btn" href="{{ route('pages.show','nasil-calisir') }}">Nasıl çalışır? <span aria-hidden="true">→</span></a>
      </div>
    </section>

    @if(count($kategoriSss))
    <section class="apt-card" style="margin-top:18px" aria-labelledby="liste-sss">
      <div class="apt-card__head">
        <span class="apt-card__icon"><x-ui-icon name="help" size="18" /></span>
        <div>
          <h2 class="apt-card__title" id="liste-sss">{{ $konu }} hakkında sıkça sorulan sorular</h2>
        </div>
      </div>
      <div class="apt-card__body">
        @foreach($kategoriSss as $q)
          <details class="faq"><summary>{{ $q['soru'] }}</summary><p>{{ $q['cevap'] }}</p></details>
        @endforeach
      </div>
    </section>
    @endif

    @if($rehberler->isNotEmpty())
    <section class="apt-card" style="margin-top:18px" aria-labelledby="hotel-list-guide-title">
      <div class="apt-card__head">
        <span class="apt-card__icon"><x-ui-icon name="book" size="18" /></span>
        <div>
          <span class="detail-eyebrow">SEÇİM REHBERİ</span>
          <h2 class="apt-card__title" id="hotel-list-guide-title">{{ $city->name }} pet oteli seçerken dikkat edilecekler</h2>
          <p class="apt-card__sub">Konaklama öncesinde ihtiyaç duyacağınız bilgileri kısa rehberlerde keşfedin.</p>
        </div>
      </div>
      <div class="apt-card__body">
        <div class="hotel-list-guide__grid">
          @foreach($rehberler as $rg)
            <a class="hotel-list-guide__card" href="{{ route('blog.show', $rg->slug) }}">
              <span class="hotel-list-guide__card-icon" aria-hidden="true">▤</span>
              <span class="hotel-list-guide__card-copy">
                <small>ANTALYA PET OTEL REHBERİ</small>
                <strong>{{ $rg->title }}</strong>
                <span class="hotel-list-guide__read">Rehberi oku <span aria-hidden="true">→</span></span>
              </span>
            </a>
          @endforeach
        </div>
      </div>
    </section>
    @endif

  </div>
</div>
@endsection
