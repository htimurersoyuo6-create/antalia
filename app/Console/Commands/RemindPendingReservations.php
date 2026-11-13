<?php

namespace App\Console\Commands;

use App\Models\Reservation;
use App\Support\Bildirim;
use Illuminate\Console\Command;

class RemindPendingReservations extends Command
{
    protected $signature = 'reservations:remind-pending';

    protected $description = 'Yanıt bekleyen rezervasyon talepleri için tesislere hatırlatma gönderir';

    public function handle(): int
    {
        $sent = 0;
        $deferred = 0;

        Reservation::query()
            ->where('status', 'pending')
            ->where('created_at', '<=', now()->subHours(4))
            ->whereNull('owner_reminded_at')
            ->with(['hotel.owner', 'room', 'pet', 'user'])
            ->chunkById(100, function ($reservations) use (&$sent, &$deferred): void {
                foreach ($reservations as $reservation) {
                    if (! $reservation->hotel || ! $reservation->pet || ! $reservation->user) {
                        $deferred++;

                        continue;
                    }

                    $ownerReplied = $reservation->messages()
                        ->where('user_id', $reservation->hotel->owner_id)
                        ->where('created_at', '>=', $reservation->created_at)
                        ->exists();

                    if ($ownerReplied) {
                        continue;
                    }

                    if (! Bildirim::rezervasyonYanitiHatirlatmasi($reservation)) {
                        $deferred++;

                        continue;
                    }

                    $reservation->forceFill(['owner_reminded_at' => now()])->save();
                    $sent++;
                }
            });

        $this->info("Gönderilen hatırlatma: {$sent}; yeniden denenecek: {$deferred}.");

        return self::SUCCESS;
    }
}
