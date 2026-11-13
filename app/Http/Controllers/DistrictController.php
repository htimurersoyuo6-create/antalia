<?php
namespace App\Http\Controllers;

use App\Models\District;
use App\Models\Hotel;
use App\Models\Setting;

/**
 * Ilce bazli pet otel sayfalari.
 *
 * Gerekce: kullanici "Konyaalti pet otel", "Alanya pet otel",
 * "Aksu pet otel" diye arar. Ilce sayfalari bu aramalari karsilar
 * ve her oteli bolge bazinda toplar. districts tablosu oteli olan
 * her ilce icin satir tutar; oteli olmayan ilce 404 doner.
 */
class DistrictController extends Controller
{
    public function canonicalOrHotel(string $slug)
    {
        if (District::query()->where('slug', $slug)->exists()) {
            return $this->show($slug);
        }

        return app(HotelController::class)->show($slug);
    }

    public function show(string $slug)
    {
        $ilce = District::query()->with('city')->where('slug', $slug)->firstOrFail();
        $siteCity = $ilce->city?->name ?: (\App\Models\Setting::al('site_city', 'Antalya') ?: 'Antalya');

        $hoteller = Hotel::active()
            ->where('district_id', $ilce->id)
            ->with(['city', 'district', 'rooms'])
            ->orderByDesc('rating_count')
            ->orderBy('name')
            ->get();

        /* Bu ilceye yakin diger ilceler (ic baglanti ve kullam kolayligi) */
        $yakin = District::query()
            ->where('id', '!=', $ilce->id)
            ->when($ilce->city_id, fn ($q) => $q->where('city_id', $ilce->city_id))
            ->whereIn('id', Hotel::active()->select('district_id')->distinct()->toBase())
            ->orderBy('name')
            ->get();

        return view('hotels.ilce', [
            'ilce' => $ilce,
            'siteCity' => $siteCity,
            'hoteller' => $hoteller,
            'yakin' => $yakin,
            'sss' => $this->sss($ilce, $hoteller),
        ]);
    }

    /**
     * Sorular yalnizca sayfadaki GERCEK veriden uretilir:
     * otel sayisi, en ucuz gunluk fiyat, kabul edilen pet turleri.
     * Uydurma sayi girmez.
     */
    private function sss(District $ilce, $hoteller): array
    {
        $adet = $hoteller->count();
        if ($adet === 0) return [];

        $liste = [];

        $liste[] = [
            'soru' => $ilce->name . ' kaç pet otel var?',
            'cevap' => $ilce->name . ' ilçesinde şu anda ' . $adet . ' adet pet otel '
                . ($adet > 1 ? 'listelenmektedir' : 'listelenmektedir') . '. '
                . 'Her tesis için oda tipi, günlük fiyat ve kabul edilen evcil hayvan türleri ayrı ayrı gösterilir.',
        ];

        $kopek = $hoteller->filter(fn($h) => (bool) $h->accepts_dogs)->count();
        $kedi  = $hoteller->filter(fn($h) => (bool) $h->accepts_cats)->count();
        if ($kopek + $kedi > 0) {
            $liste[] = [
                'soru' => $ilce->name . ' köpek ve kedi oteli kabul ediyor mu?',
                'cevap' => $ilce->name . ' içindeki tesislerden ' . $kopek . ' tanesi köpek, '
                    . $kedi . ' tanesi kedi konaklaması kabul ediyor. '
                    . 'Bazı tesisler her iki hayvan türünü birden kabul eder; bu durum filtrelerde ayrıca belirtilir.',
            ];
        }

        $fiyatlar = [];
        foreach ($hoteller as $h) foreach ($h->rooms as $o) {
            $f = (float) $o->base_price;
            if ($f > 0) $fiyatlar[] = $f;
        }
        if ($fiyatlar) {
            $enUcuz = min($fiyatlar);
            $enPahali = max($fiyatlar);
            $liste[] = [
                'soru' => $ilce->name . ' pet otellerinde günlük oda fiyatı ne kadar?',
                'cevap' => 'Listelenen tesislerde günlük konaklama ücreti ' . number_format($enUcuz, 0, ',', '.')
                    . ' TL ile ' . number_format($enPahali, 0, ',', '.') . ' TL arasında değişmektedir. '
                    . 'Fiyatlar oda tipine ve oda büyüklüğüne göre değişir; güncel tutarı tesis sayfasından görebilirsiniz.',
            ];
        }

        $liste[] = [
            'soru' => 'Rezervasyon nasıl yapılır?',
            'cevap' => Setting::al('booking_requests_enabled', '0') === '1'
                ? 'Çevrimiçi talep platforma kaydedilir. E-posta iletimi doğrulanamazsa uyarı gösterilir; '
                    . 'rezervasyon ancak işletme onayından sonra kesinleşir.'
                : 'Çevrimiçi rezervasyon talepleri şu anda alınmıyor. Müsaitlik ve konaklama koşullarını '
                    . 'ilgili işletmeyle telefon veya WhatsApp üzerinden doğrudan teyit edin.',
        ];

        return $liste;
    }
}
