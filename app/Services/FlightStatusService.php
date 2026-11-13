<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\PetTransfer;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class FlightStatusService
{
    public function lookup(string $flightNumber, ?string $date = null): ?array
    {
        $key = (string) config('services.flight_api.key');
        $base = rtrim((string) config('services.flight_api.base_url', 'http://api.aviationstack.com/v1'), '/');

        if ($key === '' || $base === '') {
            return null;
        }

        $cacheKey = 'flight:'.strtoupper($flightNumber).':'.($date ?? 'today');

        return Cache::remember($cacheKey, now()->addMinutes(10), function () use ($base, $key, $flightNumber, $date): ?array {
            try {
                $response = Http::timeout(12)->get($base.'/flights', array_filter([
                    'access_key' => $key,
                    'flight_iata' => strtoupper($flightNumber),
                    'flight_date' => $date,
                    'limit' => 1,
                ]));

                if (! $response->successful()) {
                    Log::notice('flight.lookup_failed', ['status' => $response->status()]);

                    return null;
                }

                $row = $response->json('data.0');

                if (! is_array($row)) {
                    return null;
                }

                return [
                    'flight' => $row['flight']['iata'] ?? strtoupper($flightNumber),
                    'status' => $row['flight_status'] ?? null,
                    'airline' => $row['airline']['name'] ?? null,
                    'scheduled_arrival' => $row['arrival']['scheduled'] ?? null,
                    'estimated_arrival' => $row['arrival']['estimated'] ?? null,
                    'actual_arrival' => $row['arrival']['actual'] ?? null,
                    'delay_minutes' => $row['arrival']['delay'] ?? null,
                    'terminal' => $row['arrival']['terminal'] ?? null,
                ];
            } catch (Throwable $e) {
                Log::warning('flight.exception', ['message' => $e->getMessage()]);

                return null;
            }
        });
    }

    public function syncTransfer(PetTransfer $transfer, int $bufferMinutes = 45): ?array
    {
        if (! $transfer->flight_number) {
            return null;
        }

        $info = $this->lookup($transfer->flight_number, $transfer->pickup_at?->toDateString());

        if (! $info) {
            return null;
        }

        $eta = $info['estimated_arrival'] ?? $info['scheduled_arrival'];
        $updates = ['flight_status' => $info['status'], 'flight_checked_at' => now()];

        if ($eta) {
            try {
                $pickup = \Illuminate\Support\Carbon::parse($eta)->addMinutes($bufferMinutes);
                $updates['pickup_at'] = $pickup;
            } catch (Throwable) {
                Log::notice('flight.invalid_eta', ['transfer' => $transfer->id, 'eta' => $eta]);
            }
        }

        $transfer->update($updates);

        return $info;
    }
}
