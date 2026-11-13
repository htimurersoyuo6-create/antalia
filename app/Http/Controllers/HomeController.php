<?php
namespace App\Http\Controllers;

use App\Models\District;
use App\Models\Hotel;
use App\Models\Post;
use App\Models\RoomType;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class HomeController extends Controller
{
    public function weather()
    {
        $cacheKey = 'weather.antalya.current';
        $cached = Cache::get($cacheKey);
        if ($cached && now()->diffInMinutes(\Illuminate\Support\Carbon::parse($cached['fetchedAt'])) < 15) {
            return response()->json($cached);
        }

        $stale = Cache::get('weather.antalya.last-good');
        try {
            $response = Http::withUserAgent('AntalyaPetOtel/1.0 (https://antalyapetotel.com/)')
                ->timeout(7)
                ->get('https://api.met.no/weatherapi/locationforecast/2.0/compact', [
                    'lat' => '36.8969', 'lon' => '30.7133', 'altitude' => '30',
                ])
                ->throw();

            $current = $response->json('properties.timeseries.0');
            $details = $current['data']['instant']['details'] ?? [];
            if (!isset($details['air_temperature'], $details['wind_speed'], $details['wind_from_direction'])) {
                throw new \UnexpectedValueException('Weather response did not include current conditions.');
            }

            $result = [
                'available' => true,
                'temperatureC' => (int) round((float) $details['air_temperature']),
                'windKmh' => (int) round((float) $details['wind_speed'] * 3.6),
                'windDirection' => (int) round((float) $details['wind_from_direction']) % 360,
                'condition' => $current['data']['next_1_hours']['summary']['symbol_code']
                    ?? $current['data']['next_6_hours']['summary']['symbol_code']
                    ?? 'partlycloudy_day',
                'updatedAt' => $current['time'] ?? now()->toIso8601String(),
                'fetchedAt' => now()->toIso8601String(),
                'stale' => false,
            ];
            Cache::put($cacheKey, $result, now()->addMinutes(15));
            Cache::put('weather.antalya.last-good', $result, now()->addHours(2));

            return response()->json($result);
        } catch (\Throwable $e) {
            if ($stale) {
                $stale['stale'] = true;
                return response()->json($stale);
            }

            return response()->json(['available' => false], 503);
        }
    }

    public function occupancy()
    {
        $stats = Cache::remember('occupancy.antalya.current', now()->addMinute(), fn () => $this->occupancyStats());

        return response()->json($stats)->header('Cache-Control', 'no-store');
    }

    private function occupancyStats(): array
    {
        $start = today();
        $end = $start->copy()->addDays(7);
        $startDate = $start->toDateString();
        $endDate = $end->toDateString();

        $roomCount = DB::table('hotel_room_units as u')
            ->join('hotels as h', 'h.id', '=', 'u.hotel_id')
            ->where('h.status', 'active')
            ->where('u.status', 'available')
            ->count();
        $capacityRoomNights = $roomCount * 7;
        $driver = DB::connection()->getDriverName();
        $sql = $driver === 'sqlite'
            ? 'COALESCE(SUM(MAX(0, CAST(julianday(MIN(r.check_out, ?)) - julianday(MAX(r.check_in, ?)) AS INTEGER))), 0) AS booked_nights'
            : 'COALESCE(SUM(GREATEST(0, DATEDIFF(LEAST(r.check_out, ?), GREATEST(r.check_in, ?)))), 0) AS booked_nights';
        $bookedRoomNights = (int) DB::table('reservations as r')
            ->join('hotel_room_units as u', 'u.id', '=', 'r.room_unit_id')
            ->join('hotels as h', 'h.id', '=', 'u.hotel_id')
            ->where('h.status', 'active')
            ->where('u.status', 'available')
            ->whereIn('r.status', ['pending', 'confirmed'])
            ->where('r.check_in', '<', $endDate)
            ->where('r.check_out', '>', $startDate)
            ->selectRaw($sql, [$endDate, $startDate])
            ->value('booked_nights');

        return [
            'available' => $capacityRoomNights > 0,
            'occupancyPercent' => $capacityRoomNights > 0
                ? min(100, (int) round(($bookedRoomNights / $capacityRoomNights) * 100))
                : null,
            'roomCount' => $roomCount,
            'bookedRoomNights' => $bookedRoomNights,
            'capacityRoomNights' => $capacityRoomNights,
            'periodDays' => 7,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'source' => 'site_reservations',
            'updatedAt' => now()->toIso8601String(),
        ];
    }

    public function index()
    {
        $hoteller = Hotel::active()
            ->with(['city', 'district', 'rooms'])
            ->orderByDesc('rating_count')->orderBy('name')->get();

        $doluluk = $this->occupancyStats();
        $dolulukOrani = $doluluk['occupancyPercent'];
        $dolulukBaslangic = $doluluk['startDate'];
        $dolulukBitis = $doluluk['endDate'];
        $dolulukOdaSayisi = $doluluk['roomCount'];
        $dolulukGuncellemeAt = $doluluk['updatedAt'];

        // Ana sayfa vitrininde mevcut ayın anonim otel sayfası görüntülenmelerini kullan.
        $aylikOtelHit = DB::table('site_analytics_daily')
            ->where('dimension', 'hotel_view')
            ->whereBetween('stat_date', [today()->startOfMonth()->toDateString(), today()->toDateString()])
            ->select('label')
            ->selectRaw('SUM(page_views) AS views')
            ->groupBy('label')
            ->pluck('views', 'label');

        /* Ilce bazli tesis sayilari (yalnizca oteli olan ilceler) */
        $ilceler = District::query()
            ->whereIn('id', Hotel::active()->select('district_id')->distinct()->toBase())
            ->with(['hotels' => fn($q) => $q->where('status', 'active')])
            ->orderBy('name')->get();

        /* Oda tipi ve fiyat araligi - room_types'tan GERCEK veri */
        $fiyatlar = [];
        $turSayi  = ['Köpek' => 0, 'Kedi' => 0];
        $enUcuz = null; $enPahali = null;
        foreach ($hoteller as $h) {
            if ($h->accepts_dogs) $turSayi['Köpek']++;
            if ($h->accepts_cats)  $turSayi['Kedi']++;
            foreach ($h->rooms as $o) {
                $f = (float) $o->base_price;
                if ($f <= 0) continue;
                $fiyatlar[] = $f;
                $enUcuz = $enUcuz === null ? $f : min($enUcuz, $f);
                $enPahali = $enPahali === null ? $f : max($enPahali, $f);
            }
        }
        $fiyatBant = [];
        if ($fiyatlar) {
            foreach ([
                ['min' => 0,      'max' => 700,  'ad' => '700 TL altı'],
                ['min' => 700,    'max' => 900,  'ad' => '700 - 900 TL'],
                ['min' => 900,    'max' => 1100, 'ad' => '900 - 1.100 TL'],
                ['min' => 1100,   'max' => 99999, 'ad' => '1.100 TL üzeri'],
            ] as $b) {
                $adet = count(array_filter($fiyatlar,
                    static fn($f) => $f >= $b['min'] && $f < $b['max']));
                if ($adet > 0) $fiyatBant[] = ['ad' => $b['ad'], 'adet' => $adet, 'band' => $b];
            }
        }

        /* Yayindaki yazilar: ana sayfada ic baglanti olarak kullanilir */
        $yazilar = Post::published()
            ->orderByDesc('published_at')->limit(6)
            ->get(['slug', 'title', 'excerpt', 'published_at']);

        return view('home', [
            'hoteller'   => $hoteller,
            'dolulukOrani' => $dolulukOrani,
            'dolulukBaslangic' => $dolulukBaslangic,
            'dolulukBitis' => $dolulukBitis,
            'dolulukOdaSayisi' => $dolulukOdaSayisi,
            'dolulukGuncellemeAt' => $dolulukGuncellemeAt,
            'aylikOtelHit' => $aylikOtelHit,
            'ilceler'    => $ilceler,
            'enUcuz'     => $enUcuz,
            'enPahali'   => $enPahali,
            'fiyatBant'  => $fiyatBant,
            'turSayi'    => $turSayi,
            'yazilar'    => $yazilar,
            'sss'        => $this->sss($hoteller, $ilceler, $turSayi, $enUcuz, $enPahali),
        ]);
    }

    /**
     * Ana sayfa SSS - sayfadaki gercek sayilardan uretilir.
     * Uydurma iddia yoktur.
     */
    private function sss($hoteller, $ilceler, array $turSayi, $enUcuz, $enPahali): array
    {
        $adet = $hoteller->count();
        if ($adet === 0) return [];
        $out = [];
        $ilceAdlari = $ilceler->pluck('name')->implode(', ');

        $out[] = [
            'soru' => 'Antalya’da kaç pet otel var?',
            'cevap' => 'Antalya’da şu anda ' . $adet . ' adet aktif pet otel listeleniyor. '
                . ($ilceAdlari ? 'Tesisler Antalya’da şu ilçelerde hizmet veriyor: ' . $ilceAdlari . '. ' : '')
                . 'Her tesisin oda tipleri, günlük fiyatı, '
                . 'giriş ve çıkış saati ile kabul ettiği hayvan türleri detay sayfasında yer alır.',
        ];

        if ($turSayi['Köpek'] > 0 || $turSayi['Kedi'] > 0) {
            $out[] = [
                'soru' => 'Hangi hayvan türlerini kabul eden oteller var?',
                'cevap' => 'Listelenen tesislerden ' . $turSayi['Köpek'] . ' tanesi köpek, '
                    . $turSayi['Kedi'] . ' tanesi kedi konaklaması kabul ediyor. '
                    . 'Hem köpek hem kedi kabul eden tesisler her iki rozetle de işaretlenir. '
                    . 'Köpek veya kedi filtresiyle aradığınız hayvanı kabul etmeyen tesisler '
                    . 'listelenmez.',
            ];
        }

        if ($enUcuz !== null) {
            $out[] = [
                'soru' => 'Günlük konaklama ücreti ne kadar?',
                'cevap' => 'Listelenen tesislerde günlük oda ücreti '
                    . number_format($enUcuz, 0, ',', '.') . ' TL ile '
                    . number_format($enPahali, 0, ',', '.') . ' TL arasında değişiyor. '
                    . 'Ücret oda tipine ve konaklama tarihine göre değişebilir. '
                    . 'Rezervasyon öncesinde seçtiğiniz tarih için fiyatı işletmeyle teyit edin.',
            ];
        }

        $out[] = [
            'soru' => 'Rezervasyon nasıl yapılıyor, ödeme nasıl alınıyor?',
            'cevap' => 'Seçtiğiniz tesisin detay sayfasında oda tipini ve konaklama tarihlerini '
                . 'belirleyip rezervasyon talebi gönderirsiniz. Talep doğrudan işletmeye iletilir; '
                . 'onaylandıktan sonra ödeme işletmede yapılır. Bu nedenle rezervasyonunuz '
                . 'işletmeden dönüş alınmadan tamamlanmış sayılmaz.',
        ];

        $out[] = [
            'soru' => 'Rezervasyon talebim kaç sürede sonuçlanır?',
            'cevap' => 'Talep gönderildiği anda işletmeye iletilir. İşletme uygunluk ve oda '
                . 'durumunu kontrol edip dönüş yapar. Yoğun dönemlerde ve tatil günlerinde '
                . 'dönüş süresi uzayabilir; acil konaklama için doğrudan telefonla aramanız '
                . 'daha hızlı sonuç verir.',
        ];

        $out[] = [
            'soru' => 'Giriş ve çıkış saatleri nedir?',
            'cevap' => 'Giriş ve çıkış saatleri tesise göre değişir. Seçtiğiniz tesisin detay '
                . 'sayfasındaki saatleri inceleyin ve özel tarih ya da saat taleplerini işletmeyle teyit edin.',
        ];

        $out[] = [
            'soru' => 'Aşı karnesi ve sağlık kaydı zorunlu mu?',
            'cevap' => 'Aşı ve sağlık kaydı koşulları tesise ve hayvan türüne göre değişebilir. '
                . 'Konaklama öncesinde tesis sayfasındaki kuralları okuyup gerekli belgeleri doğrudan işletmeyle teyit edin.',
        ];

        $out[] = [
            'soru' => 'Fiyat ve oda bilgileri güncel mi?',
            'cevap' => 'Fiyat, oda ve konaklama saati bilgileri işletme panelinden girilir ve '
                . 'sayfa her açılışta veritabanındaki güncel kayıttan okunur. Yine de rezervasyon '
                . 'öncesinde işletmeyle dönüş yapmanız önerilir; özel günler için oda ve fiyat '
                . 'farklılık gösterebilir.',
        ];

        return $out;
    }
}
