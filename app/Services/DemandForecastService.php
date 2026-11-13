<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\DemandForecast;
use App\Models\Hotel;
use App\Models\Reservation;
use App\Models\RevenuePricingRule;
use App\Models\RoomType;
use App\Models\RoomUnit;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DemandForecastService
{
    public const MODEL_VERSION = 'v1';

    public function generate(Hotel $hotel, int $days = 90): array
    {
        $days = max(7, min(180, $days));
        $start = Carbon::today();
        $end = Carbon::today()->addDays($days - 1);

        $totalUnits = RoomUnit::query()->where('hotel_id', $hotel->id)->count();

        if ($totalUnits === 0) {
            $totalUnits = (int) RoomType::query()->where('hotel_id', $hotel->id)->sum('units');
        }

        $totalUnits = max(1, $totalUnits);

        $reservations = Reservation::query()
            ->where('hotel_id', $hotel->id)
            ->whereIn('status', ['confirmed', 'completed'])
            ->where('check_in', '<=', $end->toDateString())
            ->where('check_out', '>', $start->toDateString())
            ->get(['id', 'check_in', 'check_out', 'room_unit_id', 'created_at']);

        $occupied = [];

        foreach ($reservations as $reservation) {
            $from = Carbon::parse($reservation->check_in)->startOfDay();
            $to = Carbon::parse($reservation->check_out)->startOfDay();

            if ($from->lt($start)) {
                $from = $start->copy();
            }

            if ($to->gt($end->copy()->addDay())) {
                $to = $end->copy()->addDay();
            }

            for ($cursor = $from->copy(); $cursor->lt($to); $cursor->addDay()) {
                $key = $cursor->toDateString();
                $occupied[$key] = ($occupied[$key] ?? 0) + 1;
            }
        }

        $paceRecent = Reservation::query()
            ->where('hotel_id', $hotel->id)
            ->where('created_at', '>=', now()->subDays(7))
            ->count();

        $pacePrev = Reservation::query()
            ->where('hotel_id', $hotel->id)
            ->whereBetween('created_at', [now()->subDays(14), now()->subDays(7)])
            ->count();

        $paceRatio = $pacePrev > 0 ? round($paceRecent / $pacePrev, 2) : ($paceRecent > 0 ? 1.5 : 1.0);

        $history = Reservation::query()
            ->where('hotel_id', $hotel->id)
            ->whereIn('status', ['confirmed', 'completed'])
            ->where('created_at', '>=', now()->subDays(365))
            ->selectRaw('MONTH(check_in) as month, COUNT(*) as total')
            ->groupBy('month')
            ->pluck('total', 'month')
            ->all();

        $historyTotal = array_sum($history);
        $historyAvg = $historyTotal > 0 ? $historyTotal / max(1, count($history)) : 0.0;

        $season = [];

        for ($month = 1; $month <= 12; $month++) {
            $count = (int) ($history[$month] ?? 0);
            $index = $historyAvg > 0 ? $count / $historyAvg : 1.0;
            $season[$month] = max(0.7, min(1.6, round($index, 2)));
        }

        $rows = [];
        $now = now();

        DB::transaction(function () use ($hotel, $days, $start, $occupied, $totalUnits, $paceRatio, $season, $historyTotal, $now, &$rows): void {
            for ($i = 0; $i < $days; $i++) {
                $date = $start->copy()->addDays($i);
                $key = $date->toDateString();
                $occupancy = min(1.0, ($occupied[$key] ?? 0) / $totalUnits);
                $seasonIndex = $season[(int) $date->format('n')] ?? 1.0;

                $occFactor = max(-0.2, min(0.35, ($occupancy - 0.55) * 0.9));
                $paceFactor = $paceRatio >= 1.2 ? min(0.12, ($paceRatio - 1) * 0.1) : ($paceRatio < 0.8 ? -0.08 : 0.0);
                $seasonFactor = max(-0.1, min(0.15, ($seasonIndex - 1) * 0.2));

                $multiplier = round(max(0.85, min(1.6, 1 + $occFactor + $paceFactor + $seasonFactor)), 2);
                $confidence = round(min(0.95, max(0.2, $historyTotal / max(30, $days * 1.5))), 2);

                $forecast = DemandForecast::query()->updateOrCreate(
                    ['hotel_id' => $hotel->id, 'forecast_date' => $key],
                    [
                        'occupancy_rate' => round($occupancy * 100, 2),
                        'booking_pace' => $paceRatio,
                        'season_index' => $seasonIndex,
                        'suggested_multiplier' => $multiplier,
                        'confidence' => $confidence,
                        'model_version' => self::MODEL_VERSION,
                        'generated_at' => $now,
                    ]
                );

                $rows[] = $forecast;
            }
        });

        Log::info('demand_forecast.generated', [
            'hotel_id' => $hotel->id,
            'days' => $days,
            'units' => $totalUnits,
            'pace_ratio' => $paceRatio,
            'history_total' => $historyTotal,
        ]);

        return $rows;
    }

    public function applyToPricing(Hotel $hotel, DemandForecast $forecast): RevenuePricingRule
    {
        if ((int) $forecast->hotel_id !== (int) $hotel->id) {
            abort(404);
        }

        $percent = (int) round(((float) $forecast->suggested_multiplier) * 100);

        return RevenuePricingRule::create([
            'hotel_id' => $hotel->id,
            'start_date' => $forecast->forecast_date->toDateString(),
            'end_date' => $forecast->forecast_date->toDateString(),
            'min_nights' => 1,
            'base_multiplier_percent' => max(50, min(300, $percent)),
            'surcharge_percent' => 0,
            'is_active' => true,
        ]);
    }
}
