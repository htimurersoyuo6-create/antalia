<?php
namespace App\Http\Controllers;

use App\Models\Hotel;
use App\Models\HotelClaim;
use App\Support\Bildirim;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class HotelClaimController extends Controller
{
    private function hotel(string $slug): Hotel
    {
        return Hotel::active()->where('slug', $slug)->with('owner')->firstOrFail();
    }

    public function create(Request $r, string $slug)
    {
        $hotel = $this->hotel($slug);
        if (!$hotel->sahipsizMi()) {
            return redirect()->route('hotels.show', $hotel->slug)->withErrors(['claim' => 'Bu tesis zaten bir işletme hesabına bağlı.']);
        }
        $bekleyen = HotelClaim::where('hotel_id', $hotel->id)->where('user_id', $r->user()->id)->where('status', 'pending')->exists();
        return view('hotels.sahiplen', ['hotel' => $hotel, 'bekleyen' => $bekleyen]);
    }

    public function store(Request $r, string $slug)
    {
        if (!\App\Support\Captcha::check($r, 'claim')) return back()->withErrors(['captcha' => 'Güvenlik kontrolü başarısız. Yeni doğrulamayı tamamlayın.'])->withInput();

        $d = $r->validate([
            'name' => 'required|string|max:120',
            'phone' => 'required|string|max:30',
            'relationship' => 'required|string|max:120',
            'license_no' => 'nullable|string|max:80',
            'note' => 'required|string|min:15|max:1500',
            'kvkk' => 'accepted',
        ], ['note.min' => 'Lütfen işletmeyle bağlantınızı en az birkaç cümleyle açıklayın.']);

        $claim = DB::transaction(function () use ($slug, $r, $d) {
            $hotel = Hotel::active()->where('slug', $slug)->with('owner')->lockForUpdate()->firstOrFail();
            if (!$hotel->sahipsizMi()) {
                throw ValidationException::withMessages(['claim' => 'Bu tesis artık sahipsiz görünmüyor; talep oluşturulamadı.']);
            }
            $alreadyPending = HotelClaim::where('hotel_id', $hotel->id)->where('user_id', $r->user()->id)->where('status', 'pending')->exists();
            if ($alreadyPending) {
                throw ValidationException::withMessages(['claim' => 'Bu tesis için zaten bekleyen bir talebiniz var.']);
            }
            return HotelClaim::create([
                'hotel_id' => $hotel->id,
                'user_id' => $r->user()->id,
                'name' => $d['name'],
                'contact_phone' => $d['phone'],
                'relationship' => $d['relationship'],
                'license_no' => $d['license_no'] ?? null,
                'message' => $d['note'],
            ]);
        });

        try { Bildirim::sahiplenmeTalebi($claim); } catch (\Throwable $e) { report($e); }
        return redirect()->route('hotels.show', $slug)->with('ok', 'Sahiplenme talebiniz alındı. Bilgilerinizi kontrol edip size e-posta ile döneceğiz.');
    }
}
