<?php

namespace App\Services;

use App\Models\Reservation;
use Carbon\Carbon;

class ReservationExtensionService
{
    public function hasCapacity(Reservation $reservation, Carbon $newCheckOut, BookingService $booking): bool
    {
        $oldCheckOut = $reservation->check_out;

        if ($reservation->room_unit_id) {
            $unitStatus = \App\Models\RoomUnit::query()
                ->where('room_type_id', $reservation->room_type_id)
                ->whereKey($reservation->room_unit_id)
                ->value('status');
            if ($unitStatus !== 'available') return false;

            $assignedConflict = Reservation::query()
                ->where('id', '!=', $reservation->id)
                ->where('room_unit_id', $reservation->room_unit_id)
                ->whereIn('status', Reservation::BLOCKING)
                ->where('check_in', '<', $newCheckOut->toDateTimeString())
                ->where('check_out', '>', $oldCheckOut->toDateTimeString())
                ->exists();

            $legacyConflict = Reservation::query()
                ->where('id', '!=', $reservation->id)
                ->where('room_type_id', $reservation->room_type_id)
                ->whereNull('room_unit_id')
                ->whereIn('status', Reservation::BLOCKING)
                ->where('check_in', '<', $newCheckOut->toDateTimeString())
                ->where('check_out', '>', $oldCheckOut->toDateTimeString())
                ->exists();

            return !$assignedConflict && !$legacyConflict;
        }

        return $booking->isAvailable($reservation->room, $oldCheckOut, $newCheckOut, $reservation->id);
    }

    public function quote(Reservation $reservation, Carbon $newCheckOut, BookingService $booking): array
    {
        $reservation->loadMissing(['room', 'extras']);
        $oldCheckOut = $reservation->check_out;
        $nights = (int) $oldCheckOut->diffInDays($newCheckOut);
        $roomTotal = $booking->totalPrice($reservation->room, $oldCheckOut, $newCheckOut);

        $currentRoomTotal = max(
            0,
            (float) $reservation->total_price
                - (float) $reservation->extras_total
                - (float) $reservation->transfer_total
                + (float) $reservation->discount_total
        );
        $discountRate = $currentRoomTotal > 0
            ? min(1, max(0, (float) $reservation->discount_total / $currentRoomTotal))
            : 0;
        $discountTotal = round($roomTotal * $discountRate, 2);
        $extrasTotal = round($reservation->extras
            ->where('unit', 'night')
            ->sum(fn ($extra) => (float) $extra->price * $nights), 2);

        return [
            'added_nights' => $nights,
            'quoted_room_total' => round($roomTotal, 2),
            'quoted_discount_total' => $discountTotal,
            'quoted_extras_total' => $extrasTotal,
            'quoted_total' => round($roomTotal - $discountTotal + $extrasTotal, 2),
        ];
    }

    public function apply(Reservation $reservation, \App\Models\ReservationExtension $extension): void
    {
        $reservation->update([
            'check_out' => $extension->requested_check_out->toDateString(),
            'nights' => $reservation->nights + $extension->added_nights,
            'total_price' => round((float) $reservation->total_price + (float) $extension->quoted_total, 2),
            'extras_total' => round((float) $reservation->extras_total + (float) $extension->quoted_extras_total, 2),
            'discount_total' => round((float) $reservation->discount_total + (float) $extension->quoted_discount_total, 2),
        ]);
    }
}
