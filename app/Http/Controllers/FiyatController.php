<?php
namespace App\Http\Controllers;

use App\Models\Hotel;

/**
 * /fiyatlar - kurulumda seçilen şehirdeki pet oteli günlük fiyat rehberi.
 *
 * VERI KURALI
 *   YALNIZCA room_types tablosundaki gercek base_price degerleri.
 *   "Piyasa ortalamasi", "sektor ortalamasi" gibi HARICI veri
 *   UYDURULMAZ; elimizde olmayan bilgi yazilmaz.
 */
class FiyatController extends Controller
{
    public function index()
    {
        $siteCity = trim((string) \App\Models\Setting::al('site_city', '')) ?: 'Antalya';
        $siteCitySlug = \Illuminate\Support\Str::slug($siteCity);
        $hoteller = Hotel::active()
            ->when($siteCitySlug !== '', fn ($q) => $q->whereHas('city', fn ($city) => $city->where('slug', $siteCitySlug)))
            ->with(['district', 'rooms'])
            ->orderBy('name')->get();

        /* Tesis bazinda gunluk en dusuk / en yuksek */
        $tesisler = [];
        $tumFiyat = [];
        foreach ($hoteller as $h) {
            $f = [];
            foreach ($h->rooms as $o) if ((float) $o->base_price > 0) $f[] = (float) $o->base_price;
            if (!$f) continue;
            $tumFiyat = array_merge($tumFiyat, $f);
            $tesisler[] = [
                'otel'  => $h,
                'adet'  => count($f),
                'min'   => min($f),
                'max'   => max($f),
                'ilce'  => $h->district->name ?? $siteCity,
                'kopek' => (bool) $h->accepts_dogs,
                'kedi'  => (bool) $h->accepts_cats,
            ];
        }
        usort($tesisler, static fn($a, $b) => $a['min'] <=> $b['min']);

        $fmt = static fn($v): string => number_format((float) $v, 0, ',', '.');

        /* Fiyat bantlari: gercek dagilima gore */
        $bantlar = [];
        foreach ([
            ['ad' => '500 - 699 TL', 'min' => 500,  'max' => 700],
            ['ad' => '700 - 899 TL', 'min' => 700,  'max' => 900],
            ['ad' => '900 TL ve üzeri', 'min' => 900, 'max' => 999999],
        ] as $b) {
            $adet = count(array_filter($tumFiyat,
                static fn($v) => $v >= $b['min'] && $v < $b['max']));
            if ($adet > 0) $bantlar[] = ['ad' => $b['ad'], 'adet' => $adet, 'band' => $b];
        }

        /* Tur bazli: kedi icin en dusuk, kopek icin en dusuk */
        $kediF = []; $kopekF = [];
        foreach ($hoteller as $h) foreach ($h->rooms as $o) {
            $v = (float) $o->base_price;
            if ($v <= 0) continue;
            if ((string) $o->pet_type === 'cat' && $h->accepts_cats)  $kediF[] = $v;
            if ((string) $o->pet_type === 'dog' && $h->accepts_dogs) $kopekF[] = $v;
        }
        /* Ilce bazli ozet.
         * ONCEKI HALI (HATA):
         *   $ilceFiyat[$t['ilce']][] = $t['min'];
         * Degerler sadece float idi; view ise $liste[0]['otel'] diye
         * nesne bekliyordu ve PHP 8 "Trying to access array offset
         * on float" hatasi verdi.
         * Simdi her ilce icin yapisal ozet tutuluyor. */
        $ilceFiyat = [];
        foreach ($tesisler as $t) {
            $il = $t['ilce'];
            if (!isset($ilceFiyat[$il])) {
                $ilceFiyat[$il] = [
                    'adet' => 0,
                    'min'  => $t['min'],
                    'slug' => $t['otel']->district->slug ?? '',
                ];
            }
            $ilceFiyat[$il]['adet']++;
            $ilceFiyat[$il]['min'] = min($ilceFiyat[$il]['min'], $t['min']);
            if ($ilceFiyat[$il]['slug'] === '' && !empty($t['otel']->district->slug))
                $ilceFiyat[$il]['slug'] = $t['otel']->district->slug;
        }
        ksort($ilceFiyat);

        $enUcuz   = $tumFiyat ? min($tumFiyat) : null;
        $enPahali = $tumFiyat ? max($tumFiyat) : null;
        $ort      = $tumFiyat ? array_sum($tumFiyat) / count($tumFiyat) : null;

        return view('hotels.fiyat', [
            'tesisler'  => $tesisler,
            'siteCity' => $siteCity,
            'bantlar'   => $bantlar,
            'enUcuz'    => $enUcuz,
            'enPahali'  => $enPahali,
            'ort'       => $ort,
            'kediEnUcuz' => $kediF ? min($kediF) : null,
            'kediAdet'   => count($kediF),
            'kopekEnUcuz'=> $kopekF ? min($kopekF) : null,
            'kopekAdet'  => count($kopekF),
            'ilceFiyat' => $ilceFiyat,
            'fmt'       => $fmt,
            'sss'       => $this->sss($tesisler, $enUcuz, $enPahali, $ort, $fmt, $siteCity, $kediF, $kopekF),
        ]);
    }

    /** PAA sorularina veriden turetilen yanitlar. */
    private function sss(array $tesisler, $ucuz, $pahali, $ort, $fmt, string $siteCity, array $kediF, array $kopekF): array
    {
        if (!$tesisler) return [];
        $out = [];

        $out[] = [
            'soru' => $siteCity . ' pet oteli fiyatları ne kadar?',
            'cevap' => 'Listelenen oda tiplerindeki gecelik ücretler '
                . $fmt($ucuz) . ' TL ile ' . $fmt($pahali) . ' TL arasında değişiyor. '
                . 'Fiyat oda tipine, konaklama tarihine ve işletme koşullarına göre değişebilir; '
                . 'seçtiğiniz tarih için tutarı rezervasyon öncesinde tesisle teyit edin.',
        ];

        if ($kediF) $out[] = [
            'soru' => $siteCity . ' kedi oteli fiyatları ne kadar?',
            'cevap' => 'Türü kedi olarak belirtilmiş odaların gecelik ücreti '
                . $fmt(min($kediF)) . ' TL ile ' . $fmt(max($kediF))
                . ' TL arasında. Güncel tutarı ve konaklama koşullarını seçtiğiniz tesisle teyit edin.',
        ];
        if ($kopekF) $out[] = [
            'soru' => $siteCity . ' köpek oteli fiyatları ne kadar?',
            'cevap' => 'Türü köpek olarak belirtilmiş odaların gecelik ücreti '
                . $fmt(min($kopekF)) . ' TL ile ' . $fmt(max($kopekF))
                . ' TL arasında. Güncel tutarı ve konaklama koşullarını seçtiğiniz tesisle teyit edin.',
        ];

        $enPahaliTesis = null;
        foreach ($tesisler as $t) if ($t['max'] >= $pahali) $enPahaliTesis = $t;
        if ($enPahaliTesis) {
            $out[] = [
                'soru' => $siteCity . '’daki en pahalı pet oteli hangisi?',
                'cevap' => 'Listemizdeki en yüksek günlük ücrete sahip tesis '
                    . $enPahaliTesis['otel']->name . ' (' . $enPahaliTesis['ilce'] . ') ve '
                    . 'günlük en yüksek oda ücreti ' . $fmt($enPahaliTesis['max'])
                    . ' TL. En düşük günlük ücret ise '
                    . $tesisler[0]['otel']->name . ' için ' . $fmt($tesisler[0]['min']) . ' TL. '
                    . 'Fiyatlar tek bir ilan değil, her tesisin kendi oda tiplerinden hesaplanmıştır.',
            ];
        }

        if ($ort !== null) {
            $out[] = [
                'soru' => 'Ortalama günlük pet oteli ücreti ne kadar?',
                'cevap' => 'Listelenen tüm oda tiplerinin günlük ücret ortalaması '
                    . $fmt($ort) . ' TL. Bu ortalamanın ağırlığı oda sayısına göre değişir; '
                    . 'çok oda tipi olan tesisler ortalamayı kendi fiyatına doğru çeker. '
                    . 'Karar verirken tek bir ortalama yerine tesisin kendi oda listesine bakın.',
            ];
        }

        $out[] = [
            'soru' => 'Fiyata ne dahil, ne dahil değil?',
            'cevap' => 'Listelenen tutar, tesislerin bildirdiği oda başı gecelik başlangıç ücretidir. '
                . 'Mama, bakım, transfer ve diğer hizmetlerin ücrete dahil olup olmadığını '
                . 'rezervasyon öncesinde ilgili işletmeyle netleştirin.',
        ];

        $out[] = [
            'soru' => 'Günlük ücret mi haftalık mı esas alınıyor?',
            'cevap' => 'Listede günlük konaklama ücreti gösterilir. Uzun konaklamalarda işletme '
                . 'kamu veya oda başına indirim uygulayabilir; ancak bu indirim tesise göre '
                . 'değişir ve standart değildir. Haftalık fiyat için doğrudan işletmeyle görüşün.',
        ];

        return $out;
    }
}
