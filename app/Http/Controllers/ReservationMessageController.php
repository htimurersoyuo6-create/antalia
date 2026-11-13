<?php
namespace App\Http\Controllers;

use App\Models\Reservation;
use App\Models\ReservationRecord;
use App\Support\Bildirim;
use Illuminate\Http\Request;

/** Misafir tarafi: rezervasyon mesajlari ve tutanak onayi. */
class ReservationMessageController extends Controller
{
    public function store(Request $r, Reservation $reservation)
    {
        abort_unless($reservation->user_id === $r->user()->id, 403);
        $d = $r->validate(['body' => 'required|string|max:1000']);
        $msg = $reservation->messages()->create(['user_id' => $r->user()->id, 'body' => trim($d['body'])]);
        Bildirim::yeniMesaj($reservation, $msg, 'musteri');
        if ($r->expectsJson()) return response()->json(['id'=>$msg->id,'sender'=>'Siz','body'=>$msg->body,'created_at'=>$msg->created_at->format('d.m.Y H:i'),'mine'=>true],201)->header('Cache-Control','no-store');
        return back()->with('ok', 'Mesajınız otele iletildi.');
    }

    public function poll(Request $r, Reservation $reservation)
    {
        abort_unless($reservation->user_id === $r->user()->id, 404);
        $rows = $reservation->messages()->where('id','>',max(0,(int)$r->query('after',0)))->with('user:id,name')->limit(100)->get();
        return response()->json(['messages'=>$rows->map(fn($m)=>['id'=>$m->id,'sender'=>$m->user_id===$r->user()->id?'Siz':$reservation->hotel->name,'body'=>$m->body,'created_at'=>$m->created_at?->format('d.m.Y H:i'),'mine'=>$m->user_id===$r->user()->id])])->header('Cache-Control','no-store');
    }

    /** Tutanagi misafir onaylar (iki tarafli onayin ikinci adimi). */
    public function approveRecord(Request $r, Reservation $reservation, ReservationRecord $record)
    {
        abort_unless($reservation->user_id === $r->user()->id && $record->reservation_id === $reservation->id, 403);
        if (!$record->customer_signed_at) $record->update(['customer_signed_at' => now()]);
        return back()->with('ok', 'Tutanağı onayladınız.');
    }
}
