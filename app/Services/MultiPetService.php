<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Pet;
use App\Models\Reservation;
use App\Models\ReservationPet;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MultiPetService
{
    public const MAX_PETS = 3;

    public function pets(Reservation $reservation)
    {
        return ReservationPet::query()
            ->with('pet')
            ->where('reservation_id', $reservation->id)
            ->orderBy('id')
            ->get();
    }

    public function add(Reservation $reservation, Pet $pet): ReservationPet
    {
        if (! in_array($reservation->status, ['pending', 'confirmed'], true)) {
            throw ValidationException::withMessages(['pet' => 'Bu rezervasyona evcil hayvan eklenemez.']);
        }

        if ((int) $pet->user_id !== (int) $reservation->user_id) {
            throw ValidationException::withMessages(['pet' => 'Yalnızca kendi evcil hayvanınızı ekleyebilirsiniz.']);
        }

        $count = ReservationPet::query()->where('reservation_id', $reservation->id)->count();

        if ($count >= self::MAX_PETS) {
            throw ValidationException::withMessages(['pet' => 'Bir rezervasyona en fazla '.self::MAX_PETS.' evcil hayvan eklenebilir.']);
        }

        if (ReservationPet::query()->where('reservation_id', $reservation->id)->where('pet_id', $pet->id)->exists()) {
            throw ValidationException::withMessages(['pet' => 'Bu evcil hayvan zaten rezervasyonda.']);
        }

        $hotel = $reservation->hotel;
        $accepts = $pet->species === 'cat' ? (bool) $hotel->accepts_cats : (bool) $hotel->accepts_dogs;

        if (! $accepts) {
            throw ValidationException::withMessages(['pet' => 'Tesis bu tür evcil hayvan kabul etmiyor.']);
        }

        $fee = $count === 0 ? 0.0 : (float) $hotel->extra_pet_fee;

        return DB::transaction(function () use ($reservation, $pet, $fee): ReservationPet {
            $link = ReservationPet::create([
                'reservation_id' => $reservation->id,
                'pet_id' => $pet->id,
                'fee' => $fee,
            ]);

            if ($fee > 0) {
                $reservation->forceFill([
                    'extras_total' => (float) $reservation->extras_total + $fee,
                    'total_price' => (float) $reservation->total_price + $fee,
                ])->save();
            }

            return $link->load('pet');
        });
    }

    public function remove(Reservation $reservation, ReservationPet $link): void
    {
        if ((int) $link->reservation_id !== (int) $reservation->id) {
            throw ValidationException::withMessages(['pet' => 'Kayıt bu rezervasyona ait değil.']);
        }

        if ($reservation->check_in && $reservation->check_in->isPast()) {
            throw ValidationException::withMessages(['pet' => 'Konaklama başladıktan sonra evcil hayvan çıkarılamaz.']);
        }

        DB::transaction(function () use ($reservation, $link): void {
            $fee = (float) $link->fee;

            if ($fee > 0) {
                $reservation->forceFill([
                    'extras_total' => max(0, (float) $reservation->extras_total - $fee),
                    'total_price' => max(0, (float) $reservation->total_price - $fee),
                ])->save();
            }

            $link->delete();
        });
    }
}
