<?php
namespace App\Http\Controllers;

use App\Models\District;
use App\Models\Hotel;
use App\Models\Post;
use App\Models\RoomType;
use App\Models\Setting;

class HotelController extends Controller
{
    /** Olanak filtresi: anahtar => [etiket, amenities kolonunda aranacak anahtar sozcukler] */
    public const OLANAKLAR = [
        'kamera'    => ['7/24 kamera', ['kamera']],
        'veteriner' => ['Veteriner desteği', ['veteriner']],
        'oyun'      => ['Oyun alanı / bahçe', ['oyun alanı', 'oyun parkı', 'bahçe']],
        'video'     => ['Fotoğraf / video raporu', ['video raporu', 'fotoğraf raporu', 'fotograf raporu']],
        'ilac'      => ['İlaç uygulaması', ['ilaç uygulaması', 'ilac uygulamasi']],
        'transfer'  => ['Transfer / pet taksi', ['transfer', 'taksi']],
        'klima'     => ['Klima', ['klima']],
        'kafessiz'  => ['Kafessiz konaklama', ['kafessiz']],
        'bireysel'  => ['Bireysel konaklama', ['bireysel konaklama']],
        'grup'      => ['Grup konaklama', ['grup konaklama']],
        'bakim'     => ['Kuaför / yıkama', ['kuaför', 'yıkama']],
        'egitim'    => ['Eğitim', ['eğitim']],
    ];

    public function legacyAntalya(\Illuminate\Http\Request $request)
    {
        $tur = mb_strtolower(trim((string) $request->query('tur', '')));
        $target = match ($tur) {
            'kedi', 'kedi-otelleri' => route('hotels.cats'),
            'köpek', 'kopek', 'kopek-otelleri' => route('hotels.dogs'),
            default => route('hotels.all'),
        };
        $query = $request->query();
        unset($query['tur']);
        if ($query) $target .= '?' . http_build_query($query);
        return redirect()->to($target, 301);
    }

    public function index(?string $city = null, ?string $type = null)
    {
        $cities = \App\Models\City::query()->orderBy('name')->get();
        /* Adres parcasi ({city}/{type?}) ONCE gelir; yoksa ?sehir= / ?tur= sorgu degeri.
         * ONCE: route parametreleri hic okunmuyordu, /antalya/kopek-otelleri tum oteli listeliyordu. */
        $citySlug = (string) ($city ?: request('sehir', 'antalya'));
        $city = $cities->firstWhere('slug', $citySlug) ?? $cities->first();

        if (!$city) {
            return view('hotels.bos', ['sehir' => $citySlug]);
        }

        $routeType = (string) ($type ?? '');
        $type = match (mb_strtolower((string) ($type ?: request('tur', '')))) {
            'köpek', 'kopek', 'kopek-otelleri' => 'kopek-otelleri',
            'kedi', 'kedi-otelleri'            => 'kedi-otelleri',
            default                            => '',
        };
        if ($routeType === '' && $type !== '' && request()->routeIs('hotels.index') && $citySlug === 'antalya') {
            $query = request()->query();
            unset($query['tur']);
            $target = $type === 'kedi-otelleri' ? route('hotels.cats') : route('hotels.dogs');
            if ($query) $target .= '?' . http_build_query($query);
            return redirect()->to($target, 301);
        }
        /* Boy filtresi yalnızca köpek odaları için geçerlidir. Oda şemasındaki `size` alanını kullan. */
        $sizeInput = request('size', '');
        $size = is_string($sizeInput) ? $sizeInput : '';
        if (!in_array($size, ['small', 'medium', 'large'], true) || $type === 'kedi-otelleri') $size = '';
        $capacityInput = request('kapasite', '');
        $capacity = is_string($capacityInput)
            ? filter_var($capacityInput, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 50]])
            : false;
        if ($capacity === false) $capacity = null;
        $roomPetType = $type === 'kopek-otelleri' ? 'dog'
            : ($type === 'kedi-otelleri' ? 'cat' : ($size !== '' ? 'dog' : null));
        $applyRoomFilters = static function ($query) use ($roomPetType, $size, $capacity): void {
            if ($roomPetType !== null) $query->where('pet_type', $roomPetType);
            if ($size !== '') $query->whereIn('size', [$size, 'any']);
            if ($capacity !== null) $query->where('capacity', '>=', $capacity);
        };
        $ilceSlug = (string) request('ilce', '');
        $sorgu = Hotel::active()->where('city_id', $city->id)->with(['city', 'district', 'rooms']);

        if ($type === 'kopek-otelleri') $sorgu->where('accepts_dogs', true);
        if ($type === 'kedi-otelleri')  $sorgu->where('accepts_cats', true);
        if ($size !== '') $sorgu->where('accepts_dogs', true);
        if ($size !== '' || $capacity !== null) $sorgu->whereHas('rooms', $applyRoomFilters);

        /* Ilce filtresi: slug ile eslenir (form select'i slug gonderir) */
        if ($ilceSlug !== '') {
            $ilce = District::query()->where('slug', $ilceSlug)->first();
            if ($ilce) {
                $sorgu->where('district_id', $ilce->id);
            } else {
                $sorgu->whereRaw('1 = 0');   /* olmayan ilce: bos sonuc */
            }
        }

        /* Serbest metin (ust arama cubugu ve 404 formu `q` gonderir; ONCE hic okunmuyordu) */
        $q = mb_substr(trim((string) request('q', '')), 0, 80);
        if ($q !== '') {
            $like = '%' . addcslashes($q, '%_\\') . '%';
            $sorgu->where(function ($w) use ($like) {
                $w->where('name', 'like', $like)->orWhere('address', 'like', $like)
                  ->orWhere('description', 'like', $like)
                  ->orWhereHas('district', fn ($d) => $d->where('name', 'like', $like));
            });
        }

        /* Minimum puan: yalnizca yorumu olan oteller */
        $puan = (float) request('puan', 0);
        if (!in_array($puan, [3.0, 4.0, 4.5], true)) $puan = 0.0;
        if ($puan > 0) $sorgu->where('rating_count', '>', 0)->where('rating_avg', '>=', $puan);

        /* Olanaklar: secilenlerin HEPSI aranir (amenities kolonunda anahtar sozcuk) */
        $secili = array_values(array_intersect((array) request('olanak', []), array_keys(self::OLANAKLAR)));
        foreach ($secili as $anahtar) {
            $kelimeler = self::OLANAKLAR[$anahtar][1];
            $sorgu->where(function ($w) use ($kelimeler) {
                foreach ($kelimeler as $k) $w->orWhere('amenities', 'like', '%' . $k . '%');
            });
        }

        /* Tarih musaitligi: giris + cikis varsa, o tarihlerde bos odasi olan oteller */
        $giris = $cikis = null;
        $tarihHata = null;
        if (request()->filled('giris') || request()->filled('cikis')) {
            if (!request()->filled('giris') || !request()->filled('cikis')) {
                $tarihHata = 'Müsaitlik aramak için giriş ve çıkış tarihlerini birlikte seçin.';
            } else {
                try {
                    $gRaw = request('giris');
                    $cRaw = request('cikis');
                    if (!is_string($gRaw) || !is_string($cRaw)) {
                        throw new \InvalidArgumentException('Tarihler YYYY-AA-GG biçiminde olmalıdır.');
                    }
                    $g = \Illuminate\Support\Carbon::createFromFormat('!Y-m-d', $gRaw);
                    $c = \Illuminate\Support\Carbon::createFromFormat('!Y-m-d', $cRaw);
                    if (!$g || !$c || $g->format('Y-m-d') !== $gRaw || $c->format('Y-m-d') !== $cRaw) {
                        throw new \InvalidArgumentException('Geçersiz takvim tarihi.');
                    }
                    if ($g->lt(today()) || $c->lte($g) || $g->diffInDays($c) > 60) {
                        $tarihHata = 'Geçerli bir tarih aralığı seçin: giriş bugünden önce olamaz, çıkış girişten sonra olmalı ve en fazla 60 gece seçilebilir.';
                    } else { $giris = $g; $cikis = $c; }
                } catch (\Throwable) {
                    $tarihHata = 'Tarihler okunamadı. Hem giriş hem çıkış tarihini seçin.';
                }
            }
        }
        $musaitOda = [];
        if ($giris) {
            $odalarSorgusu = RoomType::query();
            $applyRoomFilters($odalarSorgusu);
            $odalar = $odalarSorgusu->get();
            $musaitOda = app(\App\Services\BookingService::class)->availableRoomIds($odalar, $giris, $cikis);
            $sorgu->whereHas('rooms', fn ($x) => $x->whereIn('id', $musaitOda ?: [0]));
        }

        /* --- Fiyat araligi ve siralama ---
         * ?fiyat=500-700 -> bu araliktaki odalar (eski baglantilar icin korunur)
         * ?sirala=ucuz|pahali|puan|yorum|dogrulanmis (eski ?fiyat=ucuz|pahali da calisir)
         * Otel fiyat siralamasi, uygun oda tiplerinin MIN/MAX(base_price) degerleriyle yapilir.
         * orderBy yalnizca 'asc' / 'desc' kabul eder. */
        $fiyat  = (string) request('fiyat', '');
        $sirala = (string) request('sirala', '');
        $fiyatAraligi = null;
        $minFiyat = $maxFiyat = null;
        $fiyatHata = null;
        if (preg_match('~^(\d{1,5}(?:\.\d{1,2})?)-(\d{1,5}(?:\.\d{1,2})?)$~', $fiyat, $m)
            && (float) $m[1] <= (float) $m[2]) {
            $fiyatAraligi = [(float) $m[1], (float) $m[2]];
        } elseif ($sirala === '' && in_array($fiyat, ['ucuz', 'pahali'], true)) {
            $sirala = $fiyat;
        }
        $parsePrice = static function ($value): ?float {
            if (!is_string($value) || !preg_match('~^\d{1,6}(?:\.\d{1,2})?$~D', $value)) return null;
            return (float) $value;
        };
        $minInput = request('min_fiyat');
        $maxInput = request('max_fiyat');
        $minFiyat = $minInput === null || $minInput === '' ? null : $parsePrice($minInput);
        $maxFiyat = $maxInput === null || $maxInput === '' ? null : $parsePrice($maxInput);
        if (($minInput !== null && $minInput !== '' && $minFiyat === null)
            || ($maxInput !== null && $maxInput !== '' && $maxFiyat === null)) {
            $fiyatHata = 'Minimum ve maksimum fiyat için geçerli bir tutar girin.';
            $minFiyat = $maxFiyat = null;
        } elseif ($minFiyat !== null && $maxFiyat !== null && $minFiyat > $maxFiyat) {
            $fiyatHata = 'Minimum fiyat, maksimum fiyattan yüksek olamaz.';
            $minFiyat = $maxFiyat = null;
        }
        if (!in_array($sirala, ['ucuz', 'pahali', 'puan', 'yorum', 'dogrulanmis'], true)) $sirala = '';
        if ($fiyatAraligi !== null || $minFiyat !== null || $maxFiyat !== null) {
            $sorgu->whereHas('rooms', function ($x) use ($fiyatAraligi, $minFiyat, $maxFiyat, $applyRoomFilters) {
                $applyRoomFilters($x);
                if ($fiyatAraligi !== null) $x->whereBetween('base_price', $fiyatAraligi);
                if ($minFiyat !== null) $x->where('base_price', '>=', $minFiyat);
                if ($maxFiyat !== null) $x->where('base_price', '<=', $maxFiyat);
            });
        }

        $odaFiyatSorgusu = static function (string $aggregate) use (
            $applyRoomFilters, $fiyatAraligi, $minFiyat, $maxFiyat, $giris, $musaitOda
        ) {
            $query = RoomType::query()->selectRaw($aggregate . '(base_price)')
                ->whereColumn('room_types.hotel_id', 'hotels.id')
                ->where('base_price', '>', 0);
            $applyRoomFilters($query);
            if ($fiyatAraligi !== null) $query->whereBetween('base_price', $fiyatAraligi);
            if ($minFiyat !== null) $query->where('base_price', '>=', $minFiyat);
            if ($maxFiyat !== null) $query->where('base_price', '<=', $maxFiyat);
            if ($giris !== null) $query->whereIn('id', $musaitOda ?: [0]);

            return $query;
        };

        if ($sirala === 'pahali') {
            $sorgu->select('hotels.*')
                ->selectSub($odaFiyatSorgusu('MAX'), 'en_yuksek_oda_fiyati')
                ->orderByRaw('CASE WHEN EXISTS (SELECT 1 FROM room_types WHERE room_types.hotel_id = hotels.id AND room_types.base_price > 0) THEN 0 ELSE 1 END')
                ->orderByDesc('en_yuksek_oda_fiyati')
                ->orderBy('name');
        } elseif ($sirala === 'ucuz') {
            $sorgu->select('hotels.*')
                ->selectSub($odaFiyatSorgusu('MIN'), 'en_ucuz_fiyat')
                ->orderByRaw('CASE WHEN EXISTS (SELECT 1 FROM room_types WHERE room_types.hotel_id = hotels.id AND room_types.base_price > 0) THEN 0 ELSE 1 END')
                ->orderBy('en_ucuz_fiyat')
                ->orderBy('name');
        } elseif ($sirala === 'puan') {
            $sorgu->orderByDesc('rating_avg')->orderByDesc('rating_count')->orderBy('name');
        } elseif ($sirala === 'yorum') {
            $sorgu->orderByDesc('rating_count')->orderByDesc('rating_avg')->orderBy('name');
        } elseif ($sirala === 'dogrulanmis') {
            $sorgu->orderByRaw('CASE WHEN license_verified_at IS NULL THEN 1 ELSE 0 END')
                ->orderByDesc('rating_count')->orderByDesc('rating_avg')->orderBy('name');
        } else {
            $sorgu->orderByDesc('rating_count')->orderBy('name');
        }

        if ($fiyatAraligi !== null || $minFiyat !== null || $maxFiyat !== null || $size !== '' || $capacity !== null || $giris !== null || $roomPetType !== null) {
            $sorgu->addSelect(['rooms_min_base_price' => $odaFiyatSorgusu('MIN')]);
        }

        $hotels = $sorgu->paginate(12)->withQueryString();
        request()->attributes->set('analytics_search_result_slugs', $hotels->getCollection()->pluck('slug')->all());

        /* Tarih secildiyse her kartta o tarihler icin toplam fiyat (yalnizca musait odalarin en ucuzu) */
        $tarihli = [];
        if ($giris) {
            $svc = app(\App\Services\BookingService::class);
            $gece = (int) $giris->diffInDays($cikis);
            foreach ($hotels as $h) {
                $adaylar = $h->rooms->filter(fn ($o) => in_array($o->id, $musaitOda, true) && (float) $o->base_price > 0
                    && ($roomPetType === null || $o->pet_type === $roomPetType)
                    && ($size === '' || in_array($o->size, [$size, 'any'], true))
                    && ($capacity === null || (int) $o->capacity >= $capacity));
                if ($adaylar->isEmpty()) continue;
                $tarihli[$h->id] = ['gece' => $gece, 'toplam' => $adaylar->map(fn ($o) => $svc->totalPrice($o, $giris, $cikis))->min()];
            }
        }

        /* SSS ve diger blog yazilari icin butun sonuclar (sayfalama disi) */
        $hotelGeri = (clone $sorgu)->get();

        $ilceListesi = District::query()
            ->whereIn('id', Hotel::active()->select('district_id')->distinct()->toBase())
            ->orderBy('name')->get();

        $konu = $city->name . ' ' . ($type === 'kedi-otelleri' ? 'Kedi Otelleri'
            : ($type === 'kopek-otelleri' ? 'Köpek Otelleri' : 'Pet Otelleri'));

        $rehberler = \App\Models\Post::published()
            ->orderByDesc('published_at')->limit(6)
            ->get(['slug', 'title'])
            ->filter(fn($p) => str_contains(
                seo_slug($city->name), seo_slug($p->title)
            ) || str_contains(seo_slug($p->title), seo_slug($city->name)))
            ->values();

        /* Başlığı şehir adı içeren yazı yoksa, kutuyu boş bırakmak yerine son rehber yazıları gösterilir */
        if ($rehberler->isEmpty()) {
            $rehberler = \App\Models\Post::published()
                ->orderByDesc('published_at')->limit(4)
                ->get(['slug', 'title']);
        }

        return view('hotels.index', [
            'city' => $city,
            'hotels' => $hotels,
            'hotelGeri' => $hotelGeri,
            'type' => $type,
            'kategoriSss' => $this->kategoriSss($konu, $city, $hotelGeri, $type),
            'ilceListesi' => $ilceListesi,
            'rehberler' => $rehberler,
            'olanaklar' => self::OLANAKLAR,
            'secili' => $secili,
            'tarihli' => $tarihli,
            'tarihHata' => $tarihHata,
            'fiyatHata' => $fiyatHata,
            'filtre' => [
                'q' => $q, 'ilce' => $ilceSlug, 'puan' => $puan, 'sirala' => $sirala,
                'size' => $size, 'kapasite' => $capacity,
                'min_fiyat' => $minFiyat, 'max_fiyat' => $maxFiyat,
                'giris' => $giris?->toDateString() ?? '', 'cikis' => $cikis?->toDateString() ?? '',
            ],
        ]);
    }

    /**
     * Kategori sayfasindaki SSS.
     * Sayidaki gercek sayilardan uretilir; sabit metin kullanilmaz.
     */
    private function kategoriSss(string $konu, $city, $hoteller, string $type): array
    {
        $adet = $hoteller->count();
        if ($adet === 0) return [];
        $out = [];

        $out[] = [
            'soru' => $konu . ' arasında kaç tesis var?',
            'cevap' => $konu . ' kategorisinde şu anda ' . $adet . ' adet aktif tesis listeleniyor. '
                . 'Her tesisin oda tipleri, günlük fiyatı, giriş ve çıkış saati ile kabul ettiği '
                . 'hayvan türleri detay sayfasında ayrı ayrı verilmiştir.',
        ];

        $kopek = $hoteller->filter(fn($h) => (bool) $h->accepts_dogs)->count();
        $kedi  = $hoteller->filter(fn($h) => (bool) $h->accepts_cats)->count();
        if ($kopek + $kedi > 0) {
            $out[] = [
                'soru' => $konu . ' içinde köpek ve kedi oteli hangileri?',
                'cevap' => 'Listelenen tesislerden ' . $kopek . ' tanesi köpek, ' . $kedi . ' tanesi '
                    . 'kedi konaklaması kabul ediyor. Her iki türü birden kabul eden tesisler '
                    . 'kendi sayfasında her iki rozetle de işaretlenir; böylece kedi arayan biri '
                    . 'kabul etmeyen bir tesise yönlendirilmez.',
            ];
        }

        $fiyatlar = [];
        foreach ($hoteller as $h) foreach ($h->rooms as $o) {
            if ((float) $o->base_price > 0) $fiyatlar[] = (float) $o->base_price;
        }
        if ($fiyatlar) {
            $out[] = [
                'soru' => $konu . ' günlük fiyat aralığı nedir?',
                'cevap' => 'Günlük konaklama ücreti ' . number_format(min($fiyatlar), 0, ',', '.')
                    . (min($fiyatlar) === max($fiyatlar) ? ' TL. ' : ' TL ile ' . number_format(max($fiyatlar), 0, ',', '.') . ' TL arasında değişiyor. ')
                    . 'Ücret oda büyüklüğüne, oda tipine ve konaklanan hayvan sayısına göre farklılık '
                    . 'gösterir. Tesis detayında odalar ayrı ayrı fiyatlandırılmış şekilde listelenir.',
            ];
        }

        $out[] = [
            'soru' => 'Rezervasyon talebi nasıl gönderilir?',
            'cevap' => Setting::al('booking_requests_enabled', '0') === '1'
                ? 'Seçtiğiniz tesisin detay sayfasında çevrimiçi talep oluşturabilirsiniz. Talep platforma '
                    . 'kaydedilir; e-posta iletimi doğrulanamazsa uyarı gösterilir. Rezervasyon işletme onayıyla kesinleşir.'
                : 'Çevrimiçi rezervasyon talepleri şu anda alınmıyor. Müsaitlik ve konaklama koşullarını '
                    . 'ilgili işletmeyle doğrudan teyit edin.',
        ];

        $out[] = [
            'soru' => 'Fiyat ve oda bilgileri güncel mi?',
            'cevap' => 'Oda ve fiyat bilgilerinin işletme tarafından doğrulanma durumu, bu sayfadaki Oda tipleri bölümünde doğrulama tarihiyle gösterilir. Güncel tarih müsaitliğini ve son tutarı rezervasyon talebinde işletmeden ayrıca teyit edin.',
        ];

        return $out;
    }

    public function show(string $slug)
    {
        $hotel = Hotel::active()->where('slug', $slug)
            ->with(['city', 'district', 'rooms.inventoryUnits', 'rooms.priceRules', 'owner'])->firstOrFail();

        request()->attributes->set('analytics_hotel_slug', $hotel->slug);

        if (!auth()->check()) request()->session()->put('url.intended', route('hotels.show', $hotel->slug));

        $roomInfoUpdatedAt = $hotel->rooms
            ->flatMap(fn ($room) => collect([$room->updated_at])->merge($room->priceRules->pluck('updated_at')))
            ->filter()->max();
        $confirmedAt = $hotel->room_info_confirmed_at;
        $roomInfoStatus = !$hotel->rooms->isNotEmpty() ? 'missing'
            : (!$confirmedAt ? 'unconfirmed'
                : (($roomInfoUpdatedAt && $roomInfoUpdatedAt->greaterThan($confirmedAt)) ? 'changed'
                    : ($confirmedAt->lt(now()->subDays(30)) ? 'stale' : 'confirmed')));
        $hotel->setAttribute('room_info_updated_at', $roomInfoUpdatedAt);
        $hotel->setAttribute('room_info_status', $roomInfoStatus);

        $reviews = $hotel->reviews()
            ->where('status', 'published')
            ->with('user')->latest()->limit(20)->get();

        $pets = auth()->check() ? auth()->user()->pets()->orderBy('name')->get() : collect();

        /* --- SSS: yalnizca gercek veriden --- */
        $sss = $this->sss($hotel);

        /* --- ayni ilce / ayni sehirdeki diger oteller (ic baglanti) --- */
        $cograf = Hotel::active()
            ->where('id', '!=', $hotel->id)
            ->where(fn($q) => $q
                ->where('district_id', $hotel->district_id)
                ->orWhere('city_id', $hotel->city_id))
            ->with(['district', 'rooms'])
            ->orderByRaw('district_id = ? DESC', [$hotel->district_id])
            ->orderByDesc('rating_count')->orderBy('name')
            ->limit(6)->get();

        /* --- ilgili blog yazilari (konu eslesmesi ile) --- */
        $yazilar = $this->ilgiliYazilar($hotel);

        /* --- ayni ilceye ait diger ilce sayfalari --- */
        $ilceler = District::query()
            ->where('id', '!=', $hotel->district_id)
            ->whereIn('id', Hotel::active()->select('district_id')->distinct()->toBase())
            ->orderBy('name')->limit(10)->get();

        return view('hotels.show', [
            'hotel'   => $hotel,
            'onlineBookingEnabled' => Setting::al('booking_requests_enabled', '0') === '1'
                && $hotel->owner !== null
                && !$hotel->sahipsizMi(),
            'reviews' => $reviews,
            'pets'    => $pets,
            'sss'     => $sss,
            'cograf'  => $cograf,
            'yazilar' => $yazilar,
            'ilceler' => $ilceler,
            'extras'  => $hotel->extras()->where('active', true)->orderBy('id')->get(),
            'groomingOptions' => \App\Models\GroomingOption::where('is_active',true)->where(fn($q)=>$q->whereNull('hotel_id')->orWhere('hotel_id',$hotel->id))->orderBy('hotel_id')->orderBy('price')->get(),
            'indirimler' => $hotel->discounts()->where('active', true)->orderBy('min_nights')->get(),
        ]);
    }

    /**
     * Otelin gercek verisinden SSS uretir.
     * Icerikte olmayan bilgi iddia edilmez.
     */
    private function sss(Hotel $h): array
    {
        $ad  = (string) $h->name;
        $ilce = (string) ($h->district->name ?? 'Antalya');
        $out = [];

        /* 1) kabul edilen hayvanlar */
        $tur = [];
        if ($h->accepts_dogs) $tur[] = 'köpek';
        if ($h->accepts_cats)  $tur[] = 'kedi';
        if ($tur) {
            $out[] = [
                'soru' => $ad . ' hangi hayvanları kabul ediyor?',
                'cevap' => $ad . ' ' . ($tur === ['köpek']
                    ? 'köpek konaklaması için uygundur.'
                    : ($tur === ['kedi']
                        ? 'kedi konaklaması için uygundur.'
                        : 'hem köpek hem de kedi konaklaması için uygundur.'))
                    . ($h->has_daycare ? ' Gündüz bakımı hizmeti de verilmektedir.' : ''),
            ];
        }

        /* 2) giris cikis */
        if ($h->check_in || $h->check_out) {
            $g = $h->check_in ? date('H:i', strtotime((string) $h->check_in)) : '-';
            $c = $h->check_out ? date('H:i', strtotime((string) $h->check_out)) : '-';
            $out[] = [
                'soru' => $ad . ' giriş ve çıkış saatleri kaç?',
                'cevap' => 'Odaya giriş ' . $g . ', teslim ' . $c . ' saatlerinde yapılır. '
                    . ($h->min_stay && $h->min_stay > 1
                        ? 'En kısa konaklama ' . (int) $h->min_stay . ' gecedir.'
                        : 'Tek gece konaklama yapılabilir.'),
            ];
        }

        /* 3) gunluk fiyat */
        $fiyatlar = [];
        foreach ($h->rooms as $o) if ((float) $o->base_price > 0) $fiyatlar[] = (float) $o->base_price;
        if ($fiyatlar) {
            $out[] = [
                'soru' => $ad . ' günlük konaklama ücreti ne kadar?',
                'cevap' => 'Günlük oda ücreti ' . number_format(min($fiyatlar), 0, ',', '.') . (min($fiyatlar) === max($fiyatlar) ? ' TL. ' : ' TL ile ' . number_format(max($fiyatlar), 0, ',', '.') . ' TL arasında değişiyor. ')
                    . 'Listelenen ' . count($fiyatlar) . ' oda tipi için ücret, oda büyüklüğüne ve '
                    . 'konaklanan hayvan sayısına göre ayrı ayrı verilmiştir.',
            ];
        }

        /* 4) oda cesitliligi */
        if ($h->rooms->isNotEmpty()) {
            $adet = $h->rooms->count();
            $out[] = [
                'soru' => $ad . ' kaç çeşit oda tipi var?',
                'cevap' => 'Tesiste ' . $adet . ' oda tipi bulunuyor. '
                    . 'Odalar ' . implode(', ', $h->rooms->pluck('name')->all())
                    . ' olarak ayrılmıştır; sayfa içindeki odalar bölümünde tip, kabul edilen '
                    . 'hayvan ve günlük ücret birlikte görünür.',
            ];
        }

        /* 5) kamera / veteriner / gunluk video - olanaklardan turetilir */
        $am = $this->am($h);
        if ($am) {
            $liste = implode(', ', $am);
            $out[] = [
                'soru' => $ad . ' konaklama sırasında neler sağlanıyor?',
                'cevap' => 'İşletmenin bildirdiği olanaklar arasında ' . $liste . ' yer alıyor. Bu hizmetlerin hangilerinin '
                    . (Setting::al('booking_requests_enabled', '0') === '1'
                        ? 'konaklama süresince kullanılacağı rezervasyon talebinde belirtilmelidir.'
                        : 'konaklama süresince kullanılacağını öğrenmek için işletmeyle doğrudan görüşün.'),
            ];
        }

        /* 6) Sağlık koşulları işletmeye göre değişir; genel bir gereklilik uydurulmaz. */
        $out[] = [
            'soru' => 'Aşı ve sağlık kaydı için ne gerekiyor?',
            'cevap' => 'Aşı ve sağlık koşulları işletmeye göre değişebilir. Bu tesisin kayıtlı konaklama '
                . 'kurallarını inceleyin; burada belirtilmeyen gereklilikleri konaklama öncesinde işletmeyle teyit edin.',
        ];

        /* 7) konum */
        if ($h->address) {
            $out[] = [
                'soru' => $ad . ' nerede ve nasıl ulaşılır?',
                'cevap' => $ad . ' ' . $ilce . ' ilçesinde, ' . $h->address . ' adresinde bulunuyor. '
                    . 'İşletme iletişim bilgilerini paylaştıysa telefon ve WhatsApp bağlantıları bu sayfada görüntülenir.',
            ];
        }

        return $out;
    }

    /** amenities kolonunu duz metin listeye cevirir. */
    private function am(Hotel $h): array
    {
        $ham = trim((string) ($h->amenities ?? ''));
        if ($ham === '') return [];
        $j = json_decode($ham, true);
        if (is_array($j)) {
            return array_values(array_filter(array_map(
                static fn($x) => ucfirst(trim(mb_strtolower((string) $x))), $j)));
        }
        return array_values(array_filter(array_map('trim', explode(',', $ham))));
    }

    /**
     * Otel konusuyla iliskili yayindaki yazilari secer.
     * Konu eslesmesi basligi ve ozete bakarak puanlanir;
     * eslesme yoksa en yeni yazilara dusulur.
     *
     * DONUS TIPI: Dizi (Eloquent Collection DEGIL). Cagiran taraf
     * $yazilar->isNotEmpty() gibi bir metot cagirirsa 500 verir;
     * bu yuzden view tarafinda empty()/count() kullanilir.
     */
    private function ilgiliYazilar(Hotel $h)
    {
        $anahtar = array_filter(array_map(
            static fn($s) => mb_strtolower(trim((string) $s)),
            [
                $h->district->name ?? '',
                $h->accepts_cats ? 'kedi' : '',
                $h->accepts_dogs ? 'köpek' : '',
                $h->city->name ?? '',
            ]
        ));
        $anahtar = array_values(array_unique(array_filter($anahtar)));

        $adaylar = Post::published()
            ->orderByDesc('published_at')
            ->limit(60)
            ->get(['id', 'slug', 'title', 'excerpt', 'published_at', 'body']);

        $puanli = [];
        foreach ($adaylar as $p) {
            $metin = mb_strtolower((string) $p->title . ' ' . (string) $p->excerpt);
            $puan = 0;
            foreach ($anahtar as $a) if (mb_strpos($metin, $a) !== false) $puan++;
            if ($puan > 0) $puanli[] = ['p' => $p, 's' => $puan];
        }
        usort($puanli, static fn($a, $b) => $b['s'] <=> $a['s']);
        return array_slice(array_column($puanli, 'p'), 0, 6);
    }

    private function typeFromQuery($r): ?string
    {
        return match ($r->tur) { 'Köpek' => 'kopek-otelleri', 'Kedi' => 'kedi-otelleri', default => null };
    }
}
