<?php

namespace App\Console\Commands;

use App\Models\HotelSubscription;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ExpireHotelSubscriptions extends Command
{
    protected $signature = 'hotel-subscriptions:expire';
    protected $description = 'Süresi dolan abonelikleri ve ücretsiz denemeleri kapatır';

    public function handle(): int
    {
        $ids = HotelSubscription::where('status', 'active')->where('ends_at', '<=', now())->where(function ($q) {
            $q->where('payment_status', 'paid')->orWhere('payment_method', 'trial');
        })->pluck('id');
        foreach ($ids as $id) {
            DB::transaction(function () use ($id) {
                $subscription = HotelSubscription::whereKey($id)->lockForUpdate()->first();
                if (!$subscription || $subscription->status !== 'active' || !$subscription->ends_at?->lte(now())) return;
                $hotelId = $subscription->hotel_id;
                $subscription->update(['status' => 'expired']);
                $hasCurrent = HotelSubscription::current()->where(function ($q) use ($hotelId, $subscription) {
                    $q->where('requested_by', $subscription->requested_by);
                    if ($hotelId) $q->orWhere('hotel_id', $hotelId);
                })->exists();
                if (!$hasCurrent && $subscription->requested_by) {
                    \App\Models\Hotel::where('owner_id', $subscription->requested_by)->where('status', 'active')->update([
                        'status' => 'suspended', 'subscription_suspended' => true,
                    ]);
                } elseif (!$hasCurrent && $hotelId) {
                    // Track why the hotel was suspended so a later verified renewal
                    // can restore only subscription-suspended hotels, never hotels
                    // paused manually by an administrator.
                    \App\Models\Hotel::whereKey($hotelId)->where('status', 'active')->update([
                        'status' => 'suspended',
                        'subscription_suspended' => true,
                    ]);
                }
            });
        }
        $this->info($ids->count().' süresi dolan abonelik/deneme kontrol edildi.');
        return self::SUCCESS;
    }
}
