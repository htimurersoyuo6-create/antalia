<?php
namespace App\Http\Controllers;

use App\Models\Reservation;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function store(Request $r, Reservation $reservation)
    {
        abort_unless($reservation->user_id === $r->user()->id, 403);
        if (!$reservation->canBeReviewed()) return back()->withErrors(['rating' => 'Bu rezervasyon için yorum yapılamaz.']);
        $d = $r->validate(['rating' => 'required|integer|between:1,5', 'title' => 'nullable|string|max:150', 'comment' => 'nullable|string|max:2000']);
        /* Yalnizca tamamlanmis konaklamadan gelen (dogrulanmis misafir) yorum oldugu icin dogrudan yayinlanir */
        $reservation->review()->create(['user_id' => $r->user()->id, 'hotel_id' => $reservation->hotel_id, 'status' => 'published'] + $d);
        $reservation->hotel->refreshRating();
        return back()->with('ok', 'Yorumunuz için teşekkürler!');
    }
}
