<?php

namespace App\Http\Controllers;

use App\Models\Hotel;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class WidgetController extends Controller
{
    public function show(Request $request, Hotel $hotel)
    {
        abort_unless($hotel->status === 'active', 404);
        $hotel->loadMissing(['rooms', 'district']);

        $arrival = $this->validDate($request->query('giris')) ?? today();
        $departure = $this->validDate($request->query('cikis'));
        if (!$departure || !$departure->greaterThan($arrival)) {
            $departure = $arrival->copy()->addDay();
        }

        $response = response()->view('widgets.hotel-booking', [
            'hotel' => $hotel,
            'arrival' => $arrival->toDateString(),
            'departure' => $departure->toDateString(),
            'hasRooms' => $hotel->rooms->isNotEmpty(),
        ]);

        // The public, read-only booking widget is intentionally embeddable.
        return $response
            ->header('Content-Security-Policy', 'frame-ancestors *')
            ->header('X-Content-Type-Options', 'nosniff')
            ->header('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    private function validDate(mixed $value): ?Carbon
    {
        if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        try {
            $date = Carbon::createFromFormat('!Y-m-d', $value);
            return $date && $date->format('Y-m-d') === $value && $date->greaterThanOrEqualTo(today())
                ? $date
                : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
