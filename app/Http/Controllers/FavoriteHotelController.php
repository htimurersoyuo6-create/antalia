<?php

namespace App\Http\Controllers;

use App\Models\Hotel;
use Illuminate\Http\Request;

class FavoriteHotelController extends Controller
{
    public function index(Request $request)
    {
        $hotels = $request->user()->favoriteHotels()
            ->where('hotels.status', 'active')
            ->with(['city', 'district', 'rooms'])
            ->orderByDesc('hotel_favorites.created_at')
            ->paginate(12);

        return view('favorites.index', compact('hotels'));
    }

    public function toggle(Request $request, Hotel $hotel)
    {
        abort_unless($hotel->status === 'active', 404);

        $favorites = $request->user()->favoriteHotels();
        $saved = $favorites->where('hotels.id', $hotel->id)->exists();

        if ($saved) {
            $favorites->detach($hotel->id);
            $message = 'Otel favorilerinizden çıkarıldı.';
        } else {
            $favorites->syncWithoutDetaching([$hotel->id]);
            $message = 'Otel favorilerinize kaydedildi.';
        }

        return back()->with('ok', $message);
    }
}
