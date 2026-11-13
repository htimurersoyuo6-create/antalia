<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Hotel;
use App\Models\HotelSubscription;
use App\Models\HotelSubscriptionPayment;
use App\Models\PaymentTransaction;
use App\Models\Reservation;
use App\Models\ShopOrder;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

class BiMetricsService
{
    public function dashboard(int $ttlSeconds = 300): array
    {
        return Cache::remember('bi.dashboard.v1', $ttlSeconds, function (): array {
            $now = now();
            $last30 = $now->copy()->subDays(30)->startOfDay();
            $prev30 = $now->copy()->subDays(60)->startOfDay();

            $activeSubs = HotelSubscription::query()->with('plan:id,monthly_price,yearly_price')->where('status', 'active')->get();

            $mrr = $activeSubs->sum(function (HotelSubscription $subscription): float {
                $monthly = (float) ($subscription->plan?->monthly_price ?? 0);
                $yearly = (float) ($subscription->plan?->yearly_price ?? 0);
                $amount = (float) $subscription->amount;

                if ($subscription->billing_cycle === 'yearly') {
                    return $amount > 0 ? $amount / 12 : ($yearly > 0 ? $yearly / 12 : 0);
                }

                return $amount > 0 ? $amount : $monthly;
            });

            $arr = $mrr * 12;

            $gmv30 = (float) Reservation::query()
                ->where('payment_status', 'paid')
                ->whereIn('status', ['confirmed', 'completed'])
                ->where('created_at', '>=', $last30)
                ->sum('total_price');

            $gmvPrev = (float) Reservation::query()
                ->where('payment_status', 'paid')
                ->whereIn('status', ['confirmed', 'completed'])
                ->whereBetween('created_at', [$prev30, $last30])
                ->sum('total_price');

            $bookings30 = Reservation::query()->where('created_at', '>=', $last30)->count();
            $bookingsPrev = Reservation::query()->whereBetween('created_at', [$prev30, $last30])->count();

            $activeHotels = Hotel::query()->where('status', 'active')->count();
            $activeCustomers = User::query()->where('role', 'customer')->where('created_at', '>=', $last30)->count();

            $churnBase = HotelSubscription::query()
                ->where('ends_at', '>=', $last30)
                ->where('ends_at', '<', $now)
                ->count();
            $churned = HotelSubscription::query()
                ->where('ends_at', '>=', $last30)
                ->where('ends_at', '<', $now)
                ->where('status', '!=', 'active')
                ->count();
            $churnRate = $churnBase > 0 ? round(($churned / $churnBase) * 100, 1) : 0.0;

            $marketplaceGmv30 = (float) ShopOrder::query()
                ->where('payment_status', 'paid')
                ->where('created_at', '>=', $last30)
                ->sum('total');

            $commission30 = (float) ShopOrder::query()
                ->where('payment_status', 'paid')
                ->where('created_at', '>=', $last30)
                ->sum('commission_amount');

            $reservationCommission30 = (float) PaymentTransaction::query()
                ->whereNotNull('paid_at')
                ->where('paid_at', '>=', $last30)
                ->sum('commission_amount');

            $subscriptionRevenue30 = (float) HotelSubscriptionPayment::query()
                ->whereNotNull('paid_at')
                ->where('paid_at', '>=', $last30)
                ->sum('amount_kurus') / 100;

            $ltv = (float) Reservation::query()
                ->where('payment_status', 'paid')
                ->whereNotNull('user_id')
                ->selectRaw('COALESCE(AVG(total_price), 0) as value')
                ->value('value');

            $topHotels = Reservation::query()
                ->selectRaw('hotel_id, COUNT(*) as bookings, COALESCE(SUM(total_price), 0) as revenue')
                ->where('created_at', '>=', $last30)
                ->whereIn('status', ['confirmed', 'completed'])
                ->groupBy('hotel_id')
                ->orderByDesc('revenue')
                ->limit(8)
                ->with('hotel:id,name')
                ->get()
                ->map(fn ($row) => [
                    'name' => $row->hotel?->name ?? '#'.$row->hotel_id,
                    'bookings' => (int) $row->bookings,
                    'revenue' => (float) $row->revenue,
                ]);

            $trend = [];
            $cursor = $now->copy()->startOfMonth()->subMonths(11);

            for ($i = 0; $i < 12; $i++) {
                $start = $cursor->copy();
                $end = $cursor->copy()->endOfMonth();

                $trend[] = [
                    'label' => $start->format('m/Y'),
                    'gmv' => (float) Reservation::query()
                        ->where('payment_status', 'paid')
                        ->whereIn('status', ['confirmed', 'completed'])
                        ->whereBetween('created_at', [$start, $end])
                        ->sum('total_price'),
                    'bookings' => Reservation::query()->whereBetween('created_at', [$start, $end])->count(),
                ];

                $cursor->addMonth();
            }

            $maxGmv = max(1.0, ...array_map(fn (array $row) => $row['gmv'], $trend));

            foreach ($trend as &$row) {
                $row['height'] = max(2, (int) round(($row['gmv'] / $maxGmv) * 100));
            }
            unset($row);

            return [
                'mrr' => round($mrr, 2),
                'arr' => round($arr, 2),
                'gmv30' => round($gmv30, 2),
                'gmv_prev' => round($gmvPrev, 2),
                'gmv_change' => $gmvPrev > 0 ? round((($gmv30 - $gmvPrev) / $gmvPrev) * 100, 1) : null,
                'bookings30' => $bookings30,
                'bookings_prev' => $bookingsPrev,
                'active_hotels' => $activeHotels,
                'active_customers' => $activeCustomers,
                'churn_rate' => $churnRate,
                'marketplace_gmv30' => round($marketplaceGmv30, 2),
                'commission30' => round($commission30 + $reservationCommission30, 2),
                'subscription_revenue30' => round($subscriptionRevenue30, 2),
                'platform_revenue30' => round($commission30 + $reservationCommission30 + $subscriptionRevenue30, 2),
                'ltv' => round($ltv, 2),
                'top_hotels' => $topHotels,
                'trend' => $trend,
                'generated_at' => $now->format('d.m.Y H:i'),
            ];
        });
    }
}
