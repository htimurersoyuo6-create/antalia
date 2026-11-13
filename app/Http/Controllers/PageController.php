<?php
namespace App\Http\Controllers;

use App\Models\ContactMessage;
use Illuminate\Http\Request;

class PageController extends Controller
{
    /**
     * Icerik sayfalari.
     *
     * ONCEKI HALI: sabit 4 slug listesi + pages/{slug}.blade view.
     * SORUN: pages tablosunda 5 kayit var ve "sss" ile
     * "nasil-calisir" hicbir rotaya bagli degildi; footer'daki
     * "iptal-iade" baglantisi ise tabloda olmayan bir slug'a gidiyordu.
     *
     * YENI SIRA:
     *   1) pages tablosundan slug ara
     *   2) varsa: baslik + meta + govdeyi goster (FAQ semasi eklenir)
     *   3) yoksa: pages/{slug}.blade view'a dus (geriye donuk uyum)
     *   4) o da yoksa: 404
     */
    public function show(string $page)
    {
        $kayit = \App\Models\Page::slugda($page);

        if ($kayit) {
            return view('pages.tek', [
                'sayfa'  => $kayit,
                'baslik' => (string) $kayit->title,
            ]);
        }

        if (view()->exists('pages.' . $page)) {
            return view('pages.' . $page);
        }

        abort(404);
    }

    public function contact()
    {
        return view('pages.iletisim');
    }

    public function sendContact(Request $r)
    {
        if (!\App\Support\Captcha::check($r, 'contact')) return back()->withErrors(['captcha' => 'Güvenlik kontrolü başarısız. Yeni doğrulamayı tamamlayın.'])->withInput();

        if ($r->filled('website')) return back(); // honeypot
        $d = $r->validate([
            'name' => 'required|string|max:120', 'email' => 'required|email|max:160',
            'phone' => 'nullable|string|max:30', 'message' => 'required|string|max:3000',
        ]);
        $mesaj = ContactMessage::create($d);
        \App\Support\Bildirim::iletisim($mesaj);
        return back()->with('ok', 'Mesajınız bize ulaştı. En kısa sürede dönüş yapacağız.');
    }

    /* ================================================================
     * EKSIK IC SAYFALAR (03.10.2026, faz 2)
     * Yapısal sayfalar: /kategori, /site-haritasi,
     * /yardim-merkezi. Tüm listeler veritabanından gelir.
     * ================================================================ */

    public function kategori()
    {
        $siteCity = \App\Models\Setting::al('site_city', 'Antalya') ?: 'Antalya';
        $citySlug = \Illuminate\Support\Str::slug($siteCity);
        $otelSayi = \App\Models\Hotel::active()->count();
        $kediSayi = \App\Models\Hotel::active()->where('accepts_cats', 1)->count();
        $kopekSayi = \App\Models\Hotel::active()->where('accepts_dogs', 1)->count();

        $ilceler = \App\Models\District::query()
            ->whereIn('id', \App\Models\Hotel::active()->select('district_id')->distinct()->toBase())
            ->orderBy('name')->get()
            ->map(fn($d) => (object) [
                'name' => $d->name,
                'slug' => $d->slug,
                'otel_adet' => \App\Models\Hotel::active()->where('district_id', $d->id)->count(),
            ]);

        $odaTipleri = \Illuminate\Support\Facades\DB::table('room_types')
            ->join('hotels', 'hotels.id', '=', 'room_types.hotel_id')
            ->where('hotels.status', 'active')
            ->selectRaw('room_types.name as ad, COUNT(DISTINCT room_types.hotel_id) as otel_adet')
            ->groupBy('room_types.name')
            ->having('otel_adet', '>', 1)
            ->orderByDesc('otel_adet')
            ->orderBy('room_types.name')
            ->get()
            ->map(function ($r) {
                $r->slug = \Illuminate\Support\Str::slug($r->ad);
                return $r;
            });

        $anaKategoriler = [
            ['ad' => $siteCity . ' Pet Otelleri', 'ikon' => '🏨', 'adet' => $otelSayi,
             'url' => route('hotels.all')],
            ['ad' => 'Köpek Otelleri', 'ikon' => '🐶', 'adet' => $kopekSayi,
             'url' => route('hotels.dogs')],
            ['ad' => 'Kedi Otelleri', 'ikon' => '🐱', 'adet' => $kediSayi,
             'url' => route('hotels.cats')],
        ];

        /* Not: Türkçe kesme için U+2019 (’) kullanıldı; ASCII ' kaçışı L() dizisi içinde kaçak yapıyordu. */
        $desc = "{$siteCity}’da {$otelSayi} aktif pet otel, {$ilceler->count()} ilçe"
            . " ve oda tipine göre filtreleme. Köpek ve kedi otellerini, günlük"
            . " fiyat aralıklarını ve bölge bazlı listeyi buradan bulabilirsiniz.";

        return view('pages.kategori', compact('anaKategoriler', 'ilceler',
            'odaTipleri', 'otelSayi', 'desc'));
    }

    public function siteHaritasi()
    {
        $siteCity = \App\Models\Setting::al('site_city', 'Antalya') ?: 'Antalya';
        $citySlug = \Illuminate\Support\Str::slug($siteCity);
        $hoteller = \App\Models\Hotel::active()->orderBy('name')->get(['name', 'slug']);
        $ilceler = \App\Models\District::query()
            ->whereIn('id', \App\Models\Hotel::active()->select('district_id')->distinct()->toBase())
            ->orderBy('name')->get();
        $yazilar = \App\Models\Post::whereNotNull('published_at')
            ->orderByDesc('published_at')->limit(20)->get(['title', 'slug']);
        $sayfalar = \App\Models\Page::orderBy('title')->get(['title', 'slug']);

        $bloklar = [
            'Ana sayfa' => [
                ['ad' => 'Ana Sayfa', 'url' => route('home')],
                ['ad' => 'Fiyat Rehberi', 'url' => route('fiyatlar')],
                ['ad' => 'Kategoriler', 'url' => route('hub.kategori')],
                ['ad' => 'Yardım Merkezi', 'url' => route('hub.yardim')],
                ['ad' => 'Blog', 'url' => route('blog.index')],
                ['ad' => 'İletişim', 'url' => route('contact')],
                ['ad' => 'Otelini Kaydet', 'url' => route('partner.create')],
            ],
            'Pet otel kategorileri' => [
                ['ad' => $siteCity . ' Pet Otelleri', 'url' => route('hotels.all')],
                ['ad' => 'Köpek Otelleri', 'url' => route('hotels.dogs')],
                ['ad' => 'Kedi Otelleri', 'url' => route('hotels.cats')],
            ],
            'İlçeler' => $ilceler->map(fn($d) => ['ad' => $d->name, 'url' => url('/' . $d->slug)])->all(),
            'Pet oteller' => $hoteller->map(fn($h) => ['ad' => $h->name, 'url' => route('hotels.show', $h->slug)])->all(),
            'Blog yazıları' => $yazilar->map(fn($p) => ['ad' => $p->title, 'url' => route('blog.show', $p->slug)])->all(),
            'Yardım ve yasal' => $sayfalar->map(fn($s) => ['ad' => $s->title, 'url' => route('pages.show', $s->slug)])->all(),
        ];
        $bloklar = array_filter($bloklar, fn($b) => !empty($b));

        $desc = \App\Models\Setting::al('site_name', 'Pet Otel') . ' sitesindeki tüm sayfaların listesi: ' . $hoteller->count()
            . ' pet otel, ' . $ilceler->count() . ' ilçe, blog yazıları ve yardım sayfaları.';

        return view('pages.site-haritasi', compact('bloklar', 'desc'));
    }

    public function yardimMerkezi()
    {
        $rehberler = [
            ['ad' => 'Pet Otel Nedir?', 'ikon' => '💡', 'url' => route('pages.show', 'pet-otel-nedir'),
             'ozet' => 'Evcil hayvan konaklaması nasıl işler?'],
            ['ad' => 'Nasıl Çalışır?', 'ikon' => '📋', 'url' => route('pages.show', 'nasil-calisir'),
             'ozet' => 'Bölgeden onaya rezervasyon adımları'],
            ['ad' => 'Fiyat Rehberi', 'ikon' => '💰', 'url' => route('fiyatlar'),
             'ozet' => 'Günlük ücret aralıkları ve tesis bazlı liste'],
            ['ad' => 'Hakkımızda', 'ikon' => '👤', 'url' => route('pages.show', 'hakkimizda'),
             'ozet' => 'Tesis bilgilerini nereden alıyoruz?'],
        ];
        $islemler = [
            ['ad' => 'Sıkça Sorulan Sorular', 'ikon' => '❓', 'url' => route('pages.show', 'sss'),
             'ozet' => 'En çok merak edilenler'],
            ['ad' => 'İptal ve İade', 'ikon' => '↩️', 'url' => route('pages.show', 'iptal-iade'),
             'ozet' => 'Rezervasyonu iptal etme adımları'],
            ['ad' => 'Mesafeli Satış Politikası', 'ikon' => '📦', 'url' => route('pages.show', 'mesafeli-satis-politikasi'),
             'ozet' => 'Ön talep ve ödeme süreci'],
            ['ad' => 'İletişim', 'ikon' => '✉️', 'url' => route('contact'),
             'ozet' => 'Bize doğrudan yazın'],
        ];
        $yasal = [
            ['ad' => 'Gizlilik Politikası', 'url' => route('pages.show', 'gizlilik'),
             'ozet' => 'Hangi verileri işliyoruz?'],
            ['ad' => 'Kullanım Koşulları', 'url' => route('pages.show', 'kullanim-kosullari'),
             'ozet' => 'Platformun rolü ve sorumluluğu'],
            ['ad' => 'Çerez Politikası', 'url' => route('pages.show', 'cerez-politikasi'),
             'ozet' => 'Hangi çerezler kullanılıyor?'],
        ];

        /* SSS: sayfa govdesinden cikarilan GERCEK soru-cevap ciftleri */
        $sssKayit = \App\Models\Page::slugda('sss');
        $sss = $sssKayit && function_exists('sayfa_sss_cikar')
            ? array_slice(sayfa_sss_cikar((string) $sssKayit->content), 0, 6)
            : [];

        $desc = 'Pet otel rezervasyonu, iptal, ödeme ve gizlilik konularında tüm'
            . ' yardım sayfaları tek yerde: sıkça sorulan sorular, rehberler ve yasal metinler.';

        return view('pages.yardim-merkezi', compact('rehberler', 'islemler',
            'yasal', 'sss', 'desc'));
    }

    /* 404 sayfasi icin gercek veri (popüler ilceler + birkac otel) */
    public function hataVeri()
    {
        return [
            'ilceler' => \App\Models\District::query()
                ->whereIn('id', \App\Models\Hotel::active()->select('district_id')->distinct()->toBase())
                ->orderBy('name')->limit(8)->get(),
            'hoteller' => \App\Models\Hotel::active()
                ->with('district')->orderByDesc('rating_count')->limit(6)->get(),
        ];
    }

    /* ================================================================
     * ODA TIPI KATALOG SAYFASI (03.10.2026 faz 3)
     *
     * Yalnizca 2+ tesiste bulunan tipler acilir: tek tesiste bulunan
     * oda tipi sayfasi "ince icerik" (thin content) olur ve Google'da
     * deger kaybi yaratir.
     *
     * Tum sayfa verisi room_types + hotels tablolarindan gelir;
     * puan, yorum, sertifika gibi olcutler UYDURULMAZ.
     * ================================================================ */
    public function odaTipi(string $slug)
    {
        $ad = trim(urldecode($slug), '-');
        $siteCity = \App\Models\Setting::al('site_city', 'Antalya') ?: 'Antalya';
        $siteCitySlug = \Illuminate\Support\Str::slug($siteCity);

        $tum = \App\Models\RoomType::query()
            ->whereHas('hotel', fn($q) => $q->where('status', 'active')
                ->whereHas('city', fn($city) => $city->where('slug', $siteCitySlug)))
            ->where('base_price', '>', 0)
            ->get();

        /* slug ile eslesen ada ture (modelde slug kolonu yok) */
        $tip = null;
        $gruplar = [];
        foreach ($tum as $t) {
            $a = (string) $t->name;
            $gruplar[$a][] = $t;
            if ($tip === null && \Illuminate\Support\Str::slug($a) === $slug) $tip = $t;
        }
        if ($tip === null || count($gruplar[(string) $tip->name]) < 2) abort(404);

        $grup = $gruplar[(string) $tip->name];

        /* gunluk fiyat araligi: YALNIZCA gercek base_price */
        $fiyatlar = [];
        $turler = [];
        $olculer = [];
        $kapasite = 1;
        $otelIds = [];
        foreach ($grup as $t) {
            if ((float) $t->base_price > 0) $fiyatlar[] = (float) $t->base_price;
            if ($t->pet_type) $turler[$t->pet_type] = true;
            if ($t->size) $olculer[$t->size] = true;
            if ((int) $t->capacity > $kapasite) $kapasite = (int) $t->capacity;
            $otelIds[$t->hotel_id] = true;
        }

        $hoteller = \App\Models\Hotel::active()
            ->whereIn('id', array_keys($otelIds))
            ->with(['district', 'rooms'])
            ->orderBy('name')->get();

        $fmt = static fn($v): string => number_format((float) $v, 0, ',', '.');
        $fiyatMetin = $fiyatlar
            ? ($fmt(min($fiyatlar)) === $fmt(max($fiyatlar))
                ? $fmt(min($fiyatlar)) . ' TL'
                : $fmt(min($fiyatlar)) . ' - ' . $fmt(max($fiyatlar)) . ' TL')
            : '-';

        $turAd = ['dog' => 'köpek', 'cat' => 'kedi', 'other' => 'diğer hayvanlar'];
        $olcuAd = ['small' => 'küçük ırk', 'medium' => 'orta ırk',
                   'large' => 'büyük ırk', 'any' => 'tüm boyutlar'];

        $turMetin = implode(' ve ', array_map(
            static fn($k) => $turAd[$k] ?? $k, array_keys($turler)));
        $olcuMetin = implode(' / ', array_map(
            static fn($k) => $olcuAd[$k] ?? $k, array_keys($olculer)));

        $otelAdedi = $hoteller->count();
        $desc = "{$siteCity}’da {$otelAdedi} tesiste bulunan "
            . $tip->name . ': ' . $fiyatMetin . ' günlük ücret, '
            . ($turMetin !== '' ? $turMetin : 'evcil hayvan') . ' kabulü ve '
            . $kapasite . ' kişilik oda planı. Tesisleri karşılaştırın.';

        $ilceler = \App\Models\District::query()
            ->whereIn('id', $hoteller->pluck('district_id')->filter()->unique())
            ->orderBy('name')->get();

        return view('pages.oda-tipi', compact('tip', 'grup', 'hoteller',
            'fiyatMetin', 'fiyatlar', 'turMetin', 'olcuMetin', 'kapasite',
            'otelAdedi', 'ilceler', 'desc', 'siteCity'));
    }

    /* ================================================================
     * ODA TIPI KATALOG SAYFASI (03.10.2026 faz 3)
     *
     * Yalnizca 2+ tesiste bulunan tipler acilir: tek tesiste bulunan
     * oda tipi sayfasi "ince icerik" (thin content) olur ve Google'da
     * deger kaybi yaratir.
     *
     * Tum sayfa verisi room_types + hotels tablolarindan gelir;
     * puan, yorum, sertifika gibi olcutler UYDURULMAZ.
     * ================================================================ */}
