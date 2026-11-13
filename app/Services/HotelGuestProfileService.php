<?php

namespace App\Services;

use App\Models\HotelGuestProfile;
use App\Models\Reservation;

class HotelGuestProfileService
{
    public function recordReservation(Reservation $reservation): HotelGuestProfile
    {
        $user = $reservation->user;
        $profile = HotelGuestProfile::firstOrNew([
            'hotel_id' => $reservation->hotel_id,
            'user_id' => $reservation->user_id,
        ]);

        $profile->fill([
            'guest_name' => $user?->name ?: 'Misafir',
            'guest_phone' => $user?->phone,
            'guest_email' => $user?->email,
            'guest_address' => $user?->address,
            'first_seen_at' => $profile->first_seen_at ?: $reservation->created_at ?: now(),
            'last_seen_at' => $reservation->created_at ?: now(),
        ])->save();

        return $profile;
    }
}
