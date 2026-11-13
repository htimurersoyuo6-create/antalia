<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\FcmPushService;
use App\Models\GeofencedAlert;
use App\Models\Hotel;
use App\Models\Pet;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class GeofencedAlertService
{
    public function report(Pet $pet, float $lat, float $lng, int $radiusM, ?string $note = null): GeofencedAlert
    {
        $alert = GeofencedAlert::create([
            'pet_id' => $pet->id,
            'hotel_id' => null,
            'lat' => $lat,
            'lng' => $lng,
            'radius_m' => max(500, min(50000, $radiusM)),
            'status' => 'open',
            'reported_at' => now(),
        ]);

        $this->notify($alert, $note);

        return $alert->refresh();
    }

    public function notify(GeofencedAlert $alert, ?string $note = null): int
    {
        $hotels = $this->hotelsWithin($alert->lat, $alert->lng, $alert->radius_m);
        $hotelIds = $hotels->pluck('id')->all();
        $userIds = collect();

        if ($hotelIds !== []) {
            $userIds = User::query()
                ->where('role', 'hotel_owner')
                ->whereHas('hotels', fn ($q) => $q->whereIn('id', $hotelIds))
                ->pluck('id')
                ->merge(
                    Reservation::query()
                        ->whereIn('hotel_id', $hotelIds)
                        ->where('created_at', '>=', now()->subDays(120))
                        ->pluck('user_id')
                )
                ->unique()
                ->values();
        }

        $message = [
            'title' => 'Kayıp pet uyarısı',
            'body' => trim(($alert->pet?->name ?? 'Bir pet').' kayboldu. Bölgenizdeyse lütfen bildirin.'.($note ? ' '.$note : '')),
            'data' => [
                'type' => 'lost_pet',
                'alert_id' => (string) $alert->id,
                'lat' => (string) $alert->lat,
                'lng' => (string) $alert->lng,
                'radius_m' => (string) $alert->radius_m,
            ],
        ];

        $sent = [];

        User::query()->whereIn('id', $userIds)->chunkById(200, function ($users) use ($message, &$sent): void {
            foreach ($users as $user) {
                try {
                    FcmPushService::sendTo($user, $message);
                    $sent[] = (int) $user->id;
                } catch (Throwable $e) {
                    Log::warning('geofence.push_failed', ['user' => $user->id, 'message' => $e->getMessage()]);
                }
            }
        });

        $alert->update(['notified_user_ids' => $sent]);

        return count($sent);
    }

    public function resolve(GeofencedAlert $alert, ?int $userId = null): GeofencedAlert
    {
        $alert->update(['status' => 'resolved', 'reported_at' => $alert->reported_at]);

        return $alert->refresh();
    }

    public function hotelsWithin(float $lat, float $lng, int $radiusM)
    {
        $haversine = '(6371000 * ACOS(LEAST(1, COS(RADIANS(?)) * COS(RADIANS(lat)) * COS(RADIANS(lng) - RADIANS(?)) + SIN(RADIANS(?)) * SIN(RADIANS(lat)))))';

        return Hotel::query()
            ->whereNotNull('lat')
            ->whereNotNull('lng')
            ->whereRaw($haversine.' <= ?', [$lat, $lng, $lat, $radiusM])
            ->get();
    }
}
