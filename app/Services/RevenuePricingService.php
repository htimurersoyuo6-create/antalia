<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Hotel;
use App\Models\RevenuePricingRule;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class RevenuePricingService
{
    public function rulesFor(Hotel $hotel, CarbonInterface $date, int $nights = 1): Collection
    {
        return RevenuePricingRule::query()
            ->where('hotel_id', $hotel->id)
            ->where('is_active', true)
            ->whereDate('start_date', '<=', $date->toDateString())
            ->whereDate('end_date', '>=', $date->toDateString())
            ->where('min_nights', '<=', max(1, $nights))
            ->orderByDesc('surcharge_percent')
            ->get();
    }

    public function priceFor(Hotel $hotel, float $basePrice, CarbonInterface $date, int $nights = 1): float
    {
        $rule = $this->rulesFor($hotel, $date, $nights)->first();

        if (! $rule) {
            return round($basePrice, 2);
        }

        $multiplier = ((int) $rule->base_multiplier_percent + (float) $rule->surcharge_percent) / 100;

        return round($basePrice * max(0.1, $multiplier), 2);
    }

    public function quote(Hotel $hotel, float $basePrice, CarbonInterface $checkIn, CarbonInterface $checkOut): array
    {
        $nights = max(1, (int) $checkIn->diffInDays($checkOut));
        $total = 0.0;
        $breakdown = [];

        for ($i = 0; $i < $nights; $i++) {
            $day = $checkIn->copy()->addDays($i);
            $price = $this->priceFor($hotel, $basePrice, $day, $nights);
            $total += $price;
            $breakdown[] = ['date' => $day->toDateString(), 'price' => $price];
        }

        return [
            'nights' => $nights,
            'total' => round($total, 2),
            'average_nightly' => round($total / $nights, 2),
            'breakdown' => $breakdown,
        ];
    }
}
