<?php

namespace App\Services;

use App\Models\PaymentTransaction;
use App\Models\Reservation;
use App\Models\ReservationPaymentAttempt;
use InvalidArgumentException;

class CommissionSettlementService
{
    /**
     * Basarili bir odeme icin net otel hakedisini hesaplar ve immutable bir
     * transaction snapshot'i yazar. reservation_id unique oldugu icin tekrar
     * gelen gateway callback'leri ikinci bir tahsilat/komisyon kaydi olusturmaz.
     */
    public function recordSuccessfulPayment(
        Reservation $reservation,
        ReservationPaymentAttempt $attempt,
        string $gateway,
        string $gatewayPaymentId,
        array $gatewayMetadata = [],
    ): PaymentTransaction {
        $gross = round((float) $attempt->amount, 2);
        if ($gross <= 0) throw new InvalidArgumentException('Tahsilat tutari sifirdan buyuk olmali.');

        $rate = round((float) config('booking.platform_commission_percentage', 15), 2);
        if ($rate < 0 || $rate > 100) throw new InvalidArgumentException('Komisyon orani 0 ile 100 arasynda olmali.');

        $insurance = min($gross, (float) $reservation->insurance_fee);
        $transfer = min(max(0, $gross - $insurance), (float) $reservation->transfer_total);
        $transferRate = (float) config('booking_addons.transfer_commission_percent', 0);
        if ($transferRate < 0 || $transferRate > 100) throw new InvalidArgumentException('Transfer komisyonu 0-100 aralığında olmalı.');
        $vipDiscount = max(0, (float) $reservation->vip_discount_total);
        $platformDiscount = min($vipDiscount, max(0, (float) $reservation->vip_platform_discount_total));
        // Add the VIP reduction back to the commission base, then fund any site share by reducing commission.
        $commissionable = max(0, $gross + $vipDiscount - $insurance - $transfer);
        $baseCommission = round($commissionable * $rate / 100 + $transfer * $transferRate / 100, 2);
        $platformDiscount = min($platformDiscount, $baseCommission);
        $hotelDiscount = round($vipDiscount - $platformDiscount, 2);
        $commission = round(max(0, $baseCommission - $platformDiscount), 2);
        $providerShare = min($insurance, round($insurance * (float)config('booking_addons.insurance_provider_share_percent', 100) / 100, 2));
        $payout = round(max(0, $gross - $commission - $providerShare), 2);

        $transaction = PaymentTransaction::firstOrCreate(
            ['reservation_id' => $reservation->id],
            [
                'reservation_payment_attempt_id' => $attempt->id,
                'hotel_id' => $reservation->hotel_id,
                'user_id' => $reservation->user_id,
                'gateway' => $gateway,
                'gateway_payment_id' => $gatewayPaymentId !== '' ? $gatewayPaymentId : null,
                'currency' => $attempt->currency,
                'gross_amount' => $gross,
                'vip_discount_total' => $vipDiscount,
                'hotel_discount_total' => $hotelDiscount,
                'platform_discount_amount' => $platformDiscount,
                'commission_percentage' => $rate,
                'commission_amount' => $commission,
                'hotel_payout' => $payout,
                'insurance_provider_amount' => $providerShare,
                'status' => 'succeeded',
                'gateway_metadata' => $gatewayMetadata,
                'paid_at' => now(),
            ],
        );
        if ($transaction->wasRecentlyCreated) {
            $points = (int) floor($gross * (float) config('booking_addons.loyalty_earn_percent', 5) / 100);
            if ($points > 0) {
                \Illuminate\Support\Facades\DB::transaction(function () use ($reservation, $points) {
                    \App\Models\PointTransaction::create(['user_id'=>$reservation->user_id,'reservation_id'=>$reservation->id,'points'=>$points,'type'=>'earned','description'=>'Ödenen rezervasyondan kazanılan PotiPuan']);
                    \App\Models\User::whereKey($reservation->user_id)->increment('reward_points',$points);
                });
            }
        }
        return $transaction;
    }
}
