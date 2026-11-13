<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\PetTransfer;
use App\Services\FlightStatusService;
use Illuminate\Console\Command;

class SyncFlightTransfers extends Command
{
    protected $signature = 'flights:sync-transfers {--limit=50}';

    protected $description = 'Uçuş numarası olan transferlerin durumunu günceller ve rötar varsa pickup saatini kaydırır';

    public function handle(FlightStatusService $service): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $updated = 0;

        PetTransfer::query()
            ->whereNotNull('flight_number')
            ->where('pickup_at', '>=', now()->subHours(6))
            ->where('pickup_at', '<=', now()->addDays(3))
            ->orderBy('pickup_at')
            ->limit($limit)
            ->get()
            ->each(function (PetTransfer $transfer) use ($service, &$updated): void {
                if ($service->syncTransfer($transfer)) {
                    $updated++;
                }
            });

        $this->info("guncellenen_transfer: {$updated}");

        return self::SUCCESS;
    }
}
