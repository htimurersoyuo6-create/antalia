<?php

namespace App\Services;

use App\Models\Reservation;
use App\Models\User;

class VipTierService
{
    /** Calculate a tier only from completed, paid stays; never from pending bookings. */
    public function forUser(?User $user): array
    {
        $tiers = config('booking_addons.vip_tiers', []);
        if (!$user || !$tiers) return $this->tierData('bronze', $tiers['bronze'] ?? ['label' => 'Bronze', 'min_stays' => 0, 'discount_percent' => 0], 0, 0.0);

        $stats = Reservation::query()->where('user_id', $user->id)->where('status', 'completed')
            ->where('payment_status', 'paid')->selectRaw('COUNT(*) AS stays, COALESCE(SUM(total_price), 0) AS spend')->first();
        $stays = (int) ($stats->stays ?? 0);
        $spend = round((float) ($stats->spend ?? 0), 2);

        foreach (array_reverse($tiers, true) as $key => $tier) {
            if ($stays >= (int) ($tier['min_stays'] ?? PHP_INT_MAX)
                || ($spend >= (float) ($tier['min_spend'] ?? INF) && (float) ($tier['min_spend'] ?? 0) > 0)) {
                return $this->tierData((string) $key, $tier, $stays, $spend);
            }
        }

        return $this->tierData('bronze', $tiers['bronze'] ?? ['label' => 'Bronze', 'min_stays' => 0, 'discount_percent' => 0], $stays, $spend);
    }

    private function tierData(string $key, array $tier, int $stays, float $spend): array
    {
        return [
            'key' => $key,
            'label' => (string) ($tier['label'] ?? ucfirst($key)),
            'discount_percent' => max(0, min(100, (float) ($tier['discount_percent'] ?? 0))),
            'completed_stays' => $stays,
            'completed_spend' => $spend,
        ];
    }
}
