<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Pet;
use App\Models\PetFundEntry;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class PetFundService
{
    public function balanceCents(Pet $pet): int
    {
        return (int) PetFundEntry::query()
            ->where('pet_id', $pet->id)
            ->where('status', 'confirmed')
            ->sum('amount_cents');
    }

    public function summary(Pet $pet, int $limit = 20): array
    {
        return [
            'balance_cents' => $this->balanceCents($pet),
            'entries' => PetFundEntry::query()
                ->with('user:id,name')
                ->where('pet_id', $pet->id)
                ->latest()
                ->limit($limit)
                ->get(),
        ];
    }

    public function donate(Pet $pet, ?User $user, int $amountCents, ?string $note = null, string $status = 'confirmed'): PetFundEntry
    {
        if ($amountCents <= 0) {
            throw new InvalidArgumentException('Bağış tutarı sıfırdan büyük olmalıdır.');
        }

        if (! in_array($status, ['pending', 'confirmed'], true)) {
            throw new InvalidArgumentException('Geçersiz bağış durumu.');
        }

        return PetFundEntry::create([
            'pet_id' => $pet->id,
            'user_id' => $user?->id,
            'type' => 'donation',
            'amount_cents' => $amountCents,
            'status' => $status,
            'note' => $note,
        ]);
    }

    public function creditToReservation(Pet $pet, Reservation $reservation, int $amountCents): PetFundEntry
    {
        if ($amountCents <= 0) {
            throw new InvalidArgumentException('Kullanılacak tutar sıfırdan büyük olmalıdır.');
        }

        return DB::transaction(function () use ($pet, $reservation, $amountCents): PetFundEntry {
            $balance = $this->balanceCents($pet);

            if ($balance < $amountCents) {
                throw new InvalidArgumentException('Fon bakiyesi yetersiz.');
            }

            $entry = PetFundEntry::create([
                'pet_id' => $pet->id,
                'user_id' => $reservation->user_id,
                'reservation_id' => $reservation->id,
                'type' => 'spend',
                'amount_cents' => -$amountCents,
                'status' => 'confirmed',
                'note' => 'Rezervasyon '.$reservation->code.' için fon kullanımı',
            ]);

            Log::info('pet_fund.spent', [
                'pet_id' => $pet->id,
                'reservation_id' => $reservation->id,
                'amount_cents' => $amountCents,
                'remaining_cents' => $balance - $amountCents,
            ]);

            return $entry;
        });
    }
}
