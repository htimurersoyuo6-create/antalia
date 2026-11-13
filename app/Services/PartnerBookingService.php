<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Hotel;
use App\Models\PartnerBooking;
use App\Models\PartnerHotel;
use App\Models\Pet;
use App\Models\Reservation;
use App\Models\RoomType;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class PartnerBookingService
{
    public function __construct(private BookingService $booking)
    {
    }

    public function quote(Hotel $hotel, RoomType $room, CarbonInterface $checkIn, CarbonInterface $checkOut): array
    {
        if (! $this->booking->isAvailable($room, $checkIn, $checkOut)) {
            throw ValidationException::withMessages(['room_type_id' => 'Seçilen tarihlerde bu oda tipi müsait değil.']);
        }

        $quote = $this->booking->quote($hotel, $room, $checkIn, $checkOut);

        return [
            'room_type_id' => $room->id,
            'room_type' => $room->name,
            'check_in' => $checkIn->toDateString(),
            'check_out' => $checkOut->toDateString(),
            'nights' => (int) $checkIn->diffInDays($checkOut),
            'total' => round((float) ($quote['total'] ?? 0), 2),
            'currency' => 'TRY',
            'breakdown' => $quote,
        ];
    }

    public function create(PartnerHotel $partner, Hotel $hotel, RoomType $room, CarbonInterface $checkIn, CarbonInterface $checkOut, array $customer, array $petData, ?string $note = null): PartnerBooking
    {
        $quote = $this->quote($hotel, $room, $checkIn, $checkOut);

        return DB::transaction(function () use ($partner, $hotel, $room, $checkIn, $checkOut, $customer, $petData, $note, $quote): PartnerBooking {
            $unit = $this->booking->availableUnit($room, $checkIn, $checkOut);

            if (! $unit) {
                throw ValidationException::withMessages(['room_type_id' => 'Oda aynı anda başka bir rezervasyonla kapatıldı. Lütfen tekrar deneyin.']);
            }

            $user = $this->resolveGuest($customer);
            $pet = $this->resolvePet($user, $petData);

            $total = (float) $quote['total'];
            $reservation = Reservation::create([
                'code' => 'B2B-'.strtoupper(Str::random(8)),
                'user_id' => $user->id,
                'hotel_id' => $hotel->id,
                'room_type_id' => $room->id,
                'room_unit_id' => $unit->id,
                'pet_id' => $pet->id,
                'check_in' => $checkIn->toDateString(),
                'check_out' => $checkOut->toDateString(),
                'nights' => $quote['nights'],
                'total_price' => $total,
                'status' => 'pending',
                'payment_status' => 'unpaid',
                'payment_method' => 'partner',
                'source' => 'b2b_partner',
                'partner_hotel_id' => $partner->id,
                'customer_note' => $note,
                'hotel_note' => 'B2B partner: '.$partner->name,
            ]);

            $commissionCents = (int) round($total * ((float) $partner->commission_rate / 100) * 100);

            return PartnerBooking::create([
                'partner_hotel_id' => $partner->id,
                'reservation_id' => $reservation->id,
                'external_ref' => 'PH-'.$partner->id.'-'.$reservation->id,
                'status' => 'pending',
                'total_cents' => (int) round($total * 100),
                'commission_cents' => $commissionCents,
                'payload' => [
                    'customer' => $customer,
                    'pet' => $petData,
                    'quote' => $quote,
                    'note' => $note,
                ],
            ]);
        }, 3);
    }

    private function resolveGuest(array $customer): User
    {
        $email = strtolower(trim((string) ($customer['email'] ?? '')));

        if ($email === '') {
            $email = 'partner-'.Str::random(12).'@antalyapetotel.com';
        }

        $user = User::query()->firstOrCreate(
            ['email' => $email],
            [
                'name' => trim((string) ($customer['name'] ?? 'B2B Misafir')),
                'phone' => $customer['phone'] ?? null,
                'password' => bcrypt(Str::random(32)),
                'role' => 'customer',
            ]
        );

        return $user;
    }

    private function resolvePet(User $user, array $petData): Pet
    {
        $name = trim((string) ($petData['name'] ?? 'Misafir Pet'));

        return Pet::query()->firstOrCreate(
            ['user_id' => $user->id, 'name' => $name],
            [
                'species' => in_array($petData['species'] ?? 'dog', ['dog', 'cat', 'other'], true) ? $petData['species'] : 'dog',
                'size' => in_array($petData['size'] ?? 'medium', ['small', 'medium', 'large'], true) ? $petData['size'] : 'medium',
                'breed' => $petData['breed'] ?? null,
                'notes' => $petData['notes'] ?? null,
            ]
        );
    }

    public function confirm(PartnerBooking $booking): PartnerBooking
    {
        try {
            $booking->reservation?->update(['status' => 'confirmed']);
            $booking->update(['status' => 'confirmed']);
        } catch (Throwable $e) {
            Log::error('partner.booking_confirm_failed', ['booking' => $booking->id, 'message' => $e->getMessage()]);
            throw $e;
        }

        return $booking->refresh();
    }
}
