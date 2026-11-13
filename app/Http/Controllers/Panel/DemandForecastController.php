<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\DemandForecast;
use App\Models\Hotel;
use App\Services\DemandForecastService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DemandForecastController extends Controller
{
    public function index(Request $request, DemandForecastService $forecastService): View
    {
        $hotels = $this->hotelsFor($request->user());
        $hotel = $hotels->firstWhere('id', (int) ($request->query('hotel') ?: $hotels->first()?->id));
        abort_if(! $hotel, 404);

        $forecasts = DemandForecast::query()
            ->where('hotel_id', $hotel->id)
            ->where('forecast_date', '>=', today())
            ->orderBy('forecast_date')
            ->limit(30)
            ->get();

        if ($forecasts->isEmpty()) {
            $forecastService->generate($hotel, 60);

            $forecasts = DemandForecast::query()
                ->where('hotel_id', $hotel->id)
                ->where('forecast_date', '>=', today())
                ->orderBy('forecast_date')
                ->limit(30)
                ->get();
        }

        return view('panel.demand-forecast', [
            'hotels' => $hotels,
            'hotel' => $hotel,
            'forecasts' => $forecasts,
            'lastGenerated' => $forecasts->max('generated_at'),
        ]);
    }

    public function recalculate(Request $request, DemandForecastService $forecastService): RedirectResponse
    {
        $data = $request->validate([
            'hotel_id' => ['required', 'integer'],
            'days' => ['nullable', 'integer', 'min:7', 'max:180'],
        ]);

        $hotel = $this->hotelsFor($request->user())->firstWhere('id', (int) $data['hotel_id']);
        abort_if(! $hotel, 403);

        $rows = $forecastService->generate($hotel, (int) ($data['days'] ?? 90));

        return redirect()
            ->route('panel.forecast.index', ['hotel' => $hotel->id])
            ->with('ok', count($rows).' günlük talep tahmini güncellendi.');
    }

    public function apply(Request $request, DemandForecast $forecast, DemandForecastService $forecastService): RedirectResponse
    {
        $hotel = $this->hotelsFor($request->user())->firstWhere('id', (int) $forecast->hotel_id);
        abort_if(! $hotel, 403);

        if ($forecast->forecast_date->lt(today())) {
            return back()->withErrors(['forecast' => 'Geçmiş tarih için fiyat kuralı uygulanamaz.']);
        }

        $rule = $forecastService->applyToPricing($hotel, $forecast);

        return redirect()
            ->route('panel.forecast.index', ['hotel' => $hotel->id])
            ->with('ok', $rule->start_date->format('d.m.Y').' için %'.$rule->base_multiplier_percent.' fiyat kuralı oluşturuldu.');
    }

    private function hotelsFor($user)
    {
        return $user->isAdmin() ? Hotel::query()->orderBy('name')->get() : $user->hotels()->orderBy('name')->get();
    }
}
