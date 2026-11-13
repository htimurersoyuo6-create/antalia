<?php

namespace App\Http\Controllers;

use App\Models\District;
use App\Models\Hotel;

class LocalSeoController extends Controller
{
    public function show(string $districtSlug, string $localitySlug)
    {
        $locality = collect(config('local_seo.localities', []))->first(
            fn (array $item) => $item['district'] === $districtSlug && $item['slug'] === $localitySlug
        );

        abort_unless($locality, 404);

        $district = District::query()->with('city')->where('slug', $districtSlug)->firstOrFail();
        $hotels = Hotel::active()->where('district_id', $district->id)
            ->with(['city', 'district', 'rooms'])
            ->orderByDesc('rating_count')->orderBy('name')->get();
        abort_if($hotels->isEmpty(), 404);

        $prices = $hotels->flatMap(fn ($hotel) => $hotel->rooms
            ->pluck('base_price')->map(fn ($price) => (float) $price)->filter(fn ($price) => $price > 0));
        $nearby = collect(config('local_seo.localities', []))
            ->where('district', $districtSlug)->where('slug', '!=', $localitySlug)->values();

        $summary = $locality['name'] . ' ve ' . $district->name . ' çevresinde kedi ve köpek konaklaması arayanlar için güncel tesis rehberi.';
        if ($prices->isNotEmpty()) {
            $summary .= ' İlçedeki listelenen odaların günlük ücretleri '
                . number_format($prices->min(), 0, ',', '.') . ' TL ile '
                . number_format($prices->max(), 0, ',', '.') . ' TL arasında değişir.';
        }

        $faq = [
            ['soru' => $locality['name'] . ' pet otel seçenekleri nasıl karşılaştırılır?', 'cevap' => 'Tesislerin kabul ettiği hayvan türünü, oda tipini, günlük fiyatını ve güncel müsaitlik bilgisini birlikte inceleyin. Rezervasyon talebini göndermeden önce doğrudan tesisle teyit alın.'],
            ['soru' => $locality['name'] . ' yakınında kedi ve köpek konaklaması var mı?', 'cevap' => $district->name . ' ilçesinde aktif olarak listelenen tesisler aşağıdadır. Her tesisin kedi veya köpek kabul bilgisi kartında ayrı gösterilir.'],
            ['soru' => $locality['name'] . ' pet otel fiyatları neye göre değişir?', 'cevap' => 'Ücret; hayvan türü, oda tipi, konaklama süresi ve seçilen ek hizmetlere göre değişir. Güncel fiyatı tesis sayfasından ve tesisle görüşerek kontrol edin.'],
        ];

        return view('hotels.locality', compact('locality', 'district', 'hotels', 'prices', 'nearby', 'summary', 'faq'));
    }
}
