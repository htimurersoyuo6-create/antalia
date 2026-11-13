<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Partner;

use App\Http\Controllers\Controller;
use App\Models\Hotel;
use App\Models\PartnerBooking;
use App\Models\PartnerHotel;
use App\Models\RoomType;
use App\Services\BookingService;
use App\Services\PartnerBookingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

class PartnerApiController extends Controller
{
    public function hotels(Request $request): JsonResponse
    {
        $data = $request->validate([
            'check_in' => 'required|date|after_or_equal:today',
            'check_out' => 'required|date|after:check_in',
        ]);

        $checkIn = Carbon::parse($data['check_in']);
        $checkOut = Carbon::parse($data['check_out']);
        $booking = app(BookingService::class);

        $hotels = Hotel::query()
            ->where('status', 'active')
            ->with(['rooms' => fn ($q) => $q->where('capacity', '>=', 1)])
            ->orderByDesc('featured')
            ->orderByDesc('rating_avg')
            ->limit(50)
            ->get()
            ->map(function (Hotel $hotel) use ($booking, $checkIn, $checkOut): array {
                $rooms = $hotel->rooms->map(function (RoomType $room) use ($booking, $hotel, $checkIn, $checkOut): array {
                    $available = $booking->isAvailable($room, $checkIn, $checkOut);
                    $quote = $available ? $booking->quote($hotel, $room, $checkIn, $checkOut) : null;

                    return [
                        'room_type_id' => $room->id,
                        'name' => $room->name,
                        'pet_type' => $room->pet_type,
                        'capacity' => $room->capacity,
                        'available' => $available,
                        'nightly_from' => (float) $room->base_price,
                        'total' => $quote['total'] ?? null,
                    ];
                })->filter(fn (array $room) => $room['available'])->values();

                return [
                    'hotel_id' => $hotel->id,
                    'name' => $hotel->name,
                    'slug' => $hotel->slug,
                    'city' => $hotel->city?->name,
                    'district' => $hotel->district?->name,
                    'rating' => (float) $hotel->rating_avg,
                    'lat' => $hotel->lat,
                    'lng' => $hotel->lng,
                    'rooms' => $rooms,
                ];
            })
            ->filter(fn (array $hotel) => count($hotel['rooms']) > 0)
            ->values();

        return response()->json(['data' => $hotels, 'count' => $hotels->count()]);
    }

    public function quote(Request $request, PartnerBookingService $service): JsonResponse
    {
        $data = $request->validate([
            'hotel_id' => 'required|integer|exists:hotels,id',
            'room_type_id' => 'required|integer|exists:room_types,id',
            'check_in' => 'required|date|after_or_equal:today',
            'check_out' => 'required|date|after:check_in',
        ]);

        $hotel = Hotel::query()->where('status', 'active')->findOrFail($data['hotel_id']);
        $room = RoomType::query()->where('hotel_id', $hotel->id)->findOrFail($data['room_type_id']);

        try {
            $quote = $service->quote($hotel, $room, Carbon::parse($data['check_in']), Carbon::parse($data['check_out']));
        } catch (Throwable $e) {
            return response()->json(['error' => 'unavailable', 'message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $quote]);
    }

    public function store(Request $request, PartnerBookingService $service): JsonResponse
    {
        /** @var PartnerHotel $partner */
        $partner = $request->attributes->get('partner');

        $data = $request->validate([
            'hotel_id' => 'required|integer|exists:hotels,id',
            'room_type_id' => 'required|integer|exists:room_types,id',
            'check_in' => 'required|date|after_or_equal:today',
            'check_out' => 'required|date|after:check_in',
            'customer.name' => 'required|string|max:120',
            'customer.email' => 'nullable|email|max:160',
            'customer.phone' => 'nullable|string|max:30',
            'pet.name' => 'required|string|max:80',
            'pet.species' => 'nullable|in:dog,cat,other',
            'pet.size' => 'nullable|in:small,medium,large',
            'pet.breed' => 'nullable|string|max:80',
            'pet.notes' => 'nullable|string|max:1000',
            'note' => 'nullable|string|max:1000',
        ]);

        $hotel = Hotel::query()->where('status', 'active')->findOrFail($data['hotel_id']);
        $room = RoomType::query()->where('hotel_id', $hotel->id)->findOrFail($data['room_type_id']);

        try {
            $booking = $service->create(
                $partner,
                $hotel,
                $room,
                Carbon::parse($data['check_in']),
                Carbon::parse($data['check_out']),
                $data['customer'],
                $data['pet'],
                $data['note'] ?? null,
            );
        } catch (Throwable $e) {
            Log::warning('partner.booking_failed', ['partner' => $partner->id, 'message' => $e->getMessage()]);

            return response()->json(['error' => 'booking_failed', 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'data' => [
                'reference' => $booking->external_ref,
                'reservation_code' => $booking->reservation?->code,
                'status' => $booking->status,
                'total' => $booking->total_cents / 100,
                'commission' => $booking->commission_cents / 100,
                'currency' => 'TRY',
            ],
        ], 201);
    }

    public function show(Request $request, string $reference): JsonResponse
    {
        /** @var PartnerHotel $partner */
        $partner = $request->attributes->get('partner');

        $booking = PartnerBooking::query()
            ->with('reservation')
            ->where('partner_hotel_id', $partner->id)
            ->where('external_ref', $reference)
            ->firstOrFail();

        return response()->json([
            'data' => [
                'reference' => $booking->external_ref,
                'status' => $booking->status,
                'reservation_code' => $booking->reservation?->code,
                'reservation_status' => $booking->reservation?->status,
                'total' => $booking->total_cents / 100,
                'commission' => $booking->commission_cents / 100,
                'check_in' => $booking->reservation?->check_in?->toDateString(),
                'check_out' => $booking->reservation?->check_out?->toDateString(),
            ],
        ]);
    }

    public function cancel(Request $request, string $reference): JsonResponse
    {
        /** @var PartnerHotel $partner */
        $partner = $request->attributes->get('partner');

        $booking = PartnerBooking::query()
            ->where('partner_hotel_id', $partner->id)
            ->where('external_ref', $reference)
            ->firstOrFail();

        if ($booking->status !== 'pending') {
            return response()->json(['error' => 'not_cancellable', 'message' => 'Sadece bekleyen rezervasyonlar iptal edilebilir.'], 409);
        }

        $booking->update(['status' => 'cancelled']);
        $booking->reservation?->update(['status' => 'cancelled']);

        return response()->json(['data' => ['reference' => $booking->external_ref, 'status' => 'cancelled']]);
    }
}
