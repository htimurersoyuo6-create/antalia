<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Reservation;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class LateCheckoutService
{
    public function request(Reservation $reservation, int $hours): Reservation
    {
        if ($reservation->status !== 'confirmed') {
            throw ValidationException::withMessages(['late_checkout' => 'Yalnızca onaylı rezervasyonlar için geç çıkış talep edilebilir.']);
        }

        if ($reservation->late_checkout_status === 'requested') {
            throw ValidationException::withMessages(['late_checkout' => 'Geç çıkış talebiniz zaten değerlendirmede.']);
        }

        if ($reservation->late_checkout_status === 'approved') {
            throw ValidationException::withMessages(['late_checkout' => 'Geç çıkış onayınız zaten mevcut.']);
        }

        $hotel = $reservation->hotel;
        $maxHours = max(0, (int) $hotel->late_checkout_hours);

        if ($maxHours === 0) {
            throw ValidationException::withMessages(['late_checkout' => 'Tesis geç çıkış hizmeti sunmuyor.']);
        }

        if ($hours < 1 || $hours > $maxHours) {
            throw ValidationException::withMessages(['late_checkout' => 'Geç çıkış en fazla '.$maxHours.' saat olabilir.']);
        }

        $checkoutTime = $this->checkoutTime($reservation);
        $until = $checkoutTime->copy()->addHours($hours);

        $reservation->forceFill([
            'late_checkout_status' => 'requested',
            'late_checkout_until' => $until,
            'late_checkout_fee' => (float) $hotel->late_checkout_fee,
            'late_checkout_requested_at' => now(),
            'late_checkout_decided_at' => null,
        ])->save();

        return $reservation->refresh();
    }

    public function approve(Reservation $reservation, User $decider): Reservation
    {
        if ($reservation->late_checkout_status !== 'requested') {
            throw ValidationException::withMessages(['late_checkout' => 'Onaylanacak bir geç çıkış talebi yok.']);
        }

        $fee = (float) ($reservation->late_checkout_fee ?? 0);

        $reservation->forceFill([
            'late_checkout_status' => 'approved',
            'late_checkout_decided_at' => now(),
            'extras_total' => (float) $reservation->extras_total + $fee,
            'total_price' => (float) $reservation->total_price + $fee,
        ])->save();

        return $reservation->refresh();
    }

    public function decline(Reservation $reservation): Reservation
    {
        if ($reservation->late_checkout_status !== 'requested') {
            throw ValidationException::withMessages(['late_checkout' => 'Reddedilecek bir geç çıkış talebi yok.']);
        }

        $reservation->forceFill([
            'late_checkout_status' => 'declined',
            'late_checkout_decided_at' => now(),
            'late_checkout_until' => null,
            'late_checkout_fee' => null,
        ])->save();

        return $reservation->refresh();
    }

    private function checkoutTime(Reservation $reservation): \Illuminate\Support\Carbon
    {
        $base = $reservation->check_out instanceof \Illuminate\Support\Carbon
            ? $reservation->check_out->copy()
            : \Illuminate\Support\Carbon::parse($reservation->check_out);

        $raw = (string) ($reservation->hotel->check_out ?? '12:00');

        if (preg_match('~^(\d{1,2}):(\d{2})~', $raw, $match)) {
            $base->setTime((int) $match[1], (int) $match[2]);
        } else {
            $base->setTime(12, 0);
        }

        return $base;
    }
}
