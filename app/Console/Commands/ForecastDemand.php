<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Hotel;
use App\Services\DemandForecastService;
use Illuminate\Console\Command;
use Throwable;

class ForecastDemand extends Command
{
    protected $signature = 'forecast:demand {--days=90} {--hotel=}';

    protected $description = 'Tesisler için doluluk, rezervasyon hızı ve sezonluk verilerden fiyat çarpanı tahmini üretir';

    public function handle(DemandForecastService $forecasts): int
    {
        $days = max(7, min(180, (int) $this->option('days')));
        $hotels = $this->option('hotel')
            ? Hotel::query()->whereKey((int) $this->option('hotel'))->get()
            : Hotel::query()->where('status', 'active')->orderBy('id')->get();

        $ok = 0;
        $failed = 0;

        foreach ($hotels as $hotel) {
            try {
                $forecasts->generate($hotel, $days);
                $ok++;
            } catch (Throwable $e) {
                $failed++;
                $this->error($hotel->name.': '.$e->getMessage());
            }
        }

        $this->info("tahmin_uretildi: {$ok} · hata: {$failed}");

        return self::SUCCESS;
    }
}
