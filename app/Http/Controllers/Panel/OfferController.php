<?php
namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Hotel;
use App\Models\HotelDiscount;
use App\Models\HotelExtra;
use Illuminate\Http\Request;

/** Otel sahibi: ekstra hizmetler ve gece sayisina gore indirim (kampanya) yonetimi. */
class OfferController extends Controller
{
    private function own(Request $r, Hotel $hotel): void { abort_unless($hotel->owner_id === $r->user()->id, 403); }

    private function extraRules(): array
    {
        return ['name' => 'required|string|max:120', 'price' => 'required|numeric|gt:0|max:100000', 'unit' => 'required|in:item,stay,night', 'category' => 'required|in:'.implode(',', array_keys(HotelExtra::CATEGORIES)), 'description' => 'nullable|string|max:1000', 'available_after_booking' => 'nullable|boolean'];
    }

    public function extraStore(Request $r, Hotel $hotel)
    {
        $this->own($r, $hotel);
        if ($hotel->extras()->count() >= 20) return back()->withErrors(['extra' => 'En fazla 20 ekstra hizmet tanımlanabilir.']);
        $data = $r->validate($this->extraRules());
        $data['available_after_booking'] = $r->boolean('available_after_booking');
        $hotel->extras()->create($data + ['active' => true]);
        return back()->with('ok', 'Ekstra hizmet eklendi.');
    }

    public function extraUpdate(Request $r, Hotel $hotel, HotelExtra $extra)
    {
        $this->own($r, $hotel); abort_unless($extra->hotel_id === $hotel->id, 404);
        $data = $r->validate($this->extraRules());
        $data['available_after_booking'] = $r->boolean('available_after_booking');
        $extra->update($data + ['active' => $r->boolean('active')]);
        return back()->with('ok', 'Ekstra hizmet güncellendi.');
    }

    public function extraDestroy(Request $r, Hotel $hotel, HotelExtra $extra)
    {
        $this->own($r, $hotel); abort_unless($extra->hotel_id === $hotel->id, 404);
        $extra->delete();   // gecmis rezervasyonlardaki satirlar ad/fiyat kopyasi oldugu icin etkilenmez
        return back()->with('ok', 'Ekstra hizmet silindi.');
    }

    public function discountStore(Request $r, Hotel $hotel)
    {
        $this->own($r, $hotel);
        $d = $r->validate(['min_nights' => 'required|integer|min:2|max:60', 'percent' => 'required|integer|min:1|max:50']);
        HotelDiscount::updateOrCreate(['hotel_id' => $hotel->id, 'min_nights' => $d['min_nights']], ['percent' => $d['percent'], 'active' => true]);
        return back()->with('ok', 'Kampanya kaydedildi.');
    }

    public function discountDestroy(Request $r, Hotel $hotel, HotelDiscount $discount)
    {
        $this->own($r, $hotel); abort_unless($discount->hotel_id === $hotel->id, 404);
        $discount->delete();
        return back()->with('ok', 'Kampanya silindi.');
    }
}
