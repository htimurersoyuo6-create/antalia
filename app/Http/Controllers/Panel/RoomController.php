<?php
namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Hotel;
use App\Models\PriceRule;
use App\Models\RoomType;
use App\Models\RoomUnit;
use App\Models\Reservation;
use App\Models\User;
use App\Models\Pet;
use App\Services\BookingService;
use App\Services\HotelGuestProfileService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class RoomController extends Controller
{
    private function own(Request $r, Hotel $hotel): void { abort_unless($hotel->owner_id === $r->user()->id, 403); }

    private function invalidateRoomInfoConfirmation(Hotel $hotel): void
    {
        if ($hotel->room_info_confirmed_at) {
            $hotel->forceFill(['room_info_confirmed_at' => null])->save();
        }
    }

    private function rules(): array
    {
        return [
            'name' => 'required|string|max:120', 'pet_type' => 'required|in:dog,cat,other',
            'size' => 'required|in:small,medium,large,any', 'capacity' => 'required|integer|min:1|max:10',
            'units' => 'required|integer|min:1|max:200', 'base_price' => 'required|numeric|min:1|max:100000',
        ];
    }

    public function store(Request $r, Hotel $hotel)
    {
        $this->own($r, $hotel);
        $d=$r->validate($this->rules());
        $room=$hotel->rooms()->create($d);
        for($i=1;$i<=(int)$room->units;$i++) $room->inventoryUnits()->create(['hotel_id'=>$hotel->id,'name'=>'Oda '.$i,'status'=>'available']);
        $this->invalidateRoomInfoConfirmation($hotel);
        return back()->with('ok', 'Oda tipi eklendi.');
    }

    public function update(Request $r, Hotel $hotel, RoomType $room)
    {
        $this->own($r, $hotel); abort_unless($room->hotel_id === $hotel->id, 404);
        $d=$r->validate($this->rules());
        $current=$room->inventoryUnits()->count(); $desired=(int)$d['units'];
        if($desired<$current) {
            $drop=$current-$desired;
            $removable=$room->inventoryUnits()->where('status','available')
                ->whereDoesntHave('reservations',fn($q)=>$q->whereIn('status',['pending','confirmed']))
                ->latest('id')->limit($drop)->get();
            if($removable->count()<$drop) return back()->withErrors(['units'=>'Dolu veya bakımda olan fiziksel odaları adet azaltmadan önce yönetin.'])->withInput();
            foreach($removable as $unit) $unit->delete();
        } elseif($desired>$current) {
            $max=(int)$room->inventoryUnits()->selectRaw("MAX(CAST(SUBSTRING_INDEX(name,' ', -1) AS UNSIGNED)) as n")->value('n');
            for($i=1;$i<=$desired-$current;$i++) $room->inventoryUnits()->create(['hotel_id'=>$hotel->id,'name'=>'Oda '.(++$max),'status'=>'available']);
        }
        $room->update($d);
        $this->invalidateRoomInfoConfirmation($hotel);
        return back()->with('ok', 'Oda tipi güncellendi.');
    }

    public function updateUnit(Request $r, Hotel $hotel, RoomType $room, RoomUnit $unit)
    {
        $this->own($r,$hotel);
        abort_unless($room->hotel_id===$hotel->id && $unit->hotel_id===$hotel->id && $unit->room_type_id===$room->id,404);
        $d=$r->validate(['status'=>'required|in:available,cleaning,maintenance,renovation','note'=>'nullable|string|max:300']);
        return \Illuminate\Support\Facades\DB::transaction(function() use($room,$unit,$d) {
            RoomType::whereKey($room->id)->lockForUpdate()->first();
            $locked=RoomUnit::whereKey($unit->id)->lockForUpdate()->firstOrFail();
            if($d['status']!=='available' && $locked->reservations()->whereIn('status',['pending','confirmed'])->whereDate('check_out','>',today())->exists())
                return back()->withErrors(['unit'=>'Yaklaşan rezervasyonu olan oda satıştan kapatılamaz. Önce rezervasyonu yönetin.']);
            $locked->update($d);
            return back()->with('ok','Fiziksel oda durumu güncellendi.');
        });
    }

    public function walkIn(Request $r, Hotel $hotel, RoomType $room, RoomUnit $unit, BookingService $booking, HotelGuestProfileService $guestProfiles)
    {
        $this->own($r, $hotel);
        abort_unless($room->hotel_id === $hotel->id && $unit->hotel_id === $hotel->id && $unit->room_type_id === $room->id, 404);
        $d = $r->validate([
            'guest_name' => 'required|string|max:160',
            'guest_email' => 'required|email|max:160',
            'guest_phone' => 'nullable|string|max:30',
            'pet_name' => 'required|string|max:80',
            'pet_species' => 'required|in:dog,cat,other',
            'pet_size' => 'required|in:small,medium,large',
            'pet_breed' => 'nullable|string|max:80',
            'pet_notes' => 'nullable|string|max:1000',
            'check_in' => 'required|date_format:Y-m-d|after_or_equal:today',
            'check_out' => 'required|date_format:Y-m-d|after:check_in',
        ]);
        if ((int) \Carbon\Carbon::parse($d['check_in'])->diffInDays(\Carbon\Carbon::parse($d['check_out'])) > 60) {
            return back()->withErrors(['check_out' => 'Konaklama en fazla 60 gece olabilir.'])->withInput();
        }
        if ($room->pet_type !== $d['pet_species'] || ($d['pet_species'] === 'dog' && $room->size !== 'any' && $room->size !== $d['pet_size'])) {
            return back()->withErrors(['pet_species' => 'Seçilen hayvan bilgisi bu oda tipiyle uyumlu değil.'])->withInput();
        }

        $email = mb_strtolower(trim($d['guest_email']));
        $existingUser = User::where('email', $email)->first();
        if ($existingUser && $existingUser->role !== 'customer') {
            return back()->withErrors(['guest_email' => 'Bu e-posta misafir hesabı olarak kullanılamıyor.'])->withInput();
        }
        $checkIn = \Carbon\Carbon::parse($d['check_in'])->startOfDay();
        $checkOut = \Carbon\Carbon::parse($d['check_out'])->startOfDay();

        $reservation = DB::transaction(function () use ($d, $email, $existingUser, $hotel, $room, $unit, $checkIn, $checkOut, $booking, $guestProfiles) {
            RoomType::whereKey($room->id)->lockForUpdate()->firstOrFail();
            $lockedUnit = RoomUnit::whereKey($unit->id)->lockForUpdate()->firstOrFail();
            if ($lockedUnit->status !== 'available' || Reservation::where('room_unit_id', $lockedUnit->id)
                ->whereIn('status', Reservation::BLOCKING)->whereDate('check_in', '<', $checkOut->toDateString())
                ->whereDate('check_out', '>', $checkIn->toDateString())->exists()) return null;

            $user = $existingUser ?: User::create([
                'name' => trim($d['guest_name']), 'email' => $email,
                'phone' => trim((string) ($d['guest_phone'] ?? '')) ?: null,
                'password' => Hash::make(Str::random(48)),
            ]);
            $user->forceFill([
                'name' => trim($d['guest_name']),
                'phone' => trim((string) ($d['guest_phone'] ?? '')) ?: $user->phone,
            ])->save();

            $pet = Pet::where('user_id', $user->id)->where('name', trim($d['pet_name']))->where('species', $d['pet_species'])->first();
            if (!$pet) $pet = Pet::create([
                'user_id' => $user->id, 'name' => trim($d['pet_name']), 'species' => $d['pet_species'],
                'size' => $d['pet_size'], 'breed' => trim((string) ($d['pet_breed'] ?? '')) ?: null,
                'notes' => trim((string) ($d['pet_notes'] ?? '')) ?: null,
            ]);

            $nights = (int) $checkIn->diffInDays($checkOut);
            $quote = $booking->quote($hotel, $room, $checkIn, $checkOut);
            $reservation = Reservation::create([
                'code' => Reservation::makeCode(), 'user_id' => $user->id, 'hotel_id' => $hotel->id,
                'room_type_id' => $room->id, 'room_unit_id' => $lockedUnit->id, 'pet_id' => $pet->id,
                'check_in' => $checkIn, 'check_out' => $checkOut, 'nights' => $nights,
                'total_price' => $quote['total'], 'discount_total' => $quote['discount_total'],
                'extras_total' => 0, 'status' => 'confirmed', 'payment_status' => 'unpaid',
                'payment_method' => 'pay_at_hotel', 'customer_note' => 'İşletme panelinden oluşturulan giriş kaydı.',
            ]);
            $reservation->setRelation('user', $user);
            $guestProfiles->recordReservation($reservation);
            return $reservation;
        });

        if (!$reservation) return back()->withErrors(['unit' => 'Bu oda seçilen tarihlerde dolu, kapalı veya başka bir kayıt tarafından tutuluyor.'])->withInput();
        return redirect()->route('panel.reservations.show', $reservation)->with('ok', 'Misafir ve pet giriş kaydı oluşturuldu. Ödeme durumu: alınmadı.');
    }

    public function destroy(Request $r, Hotel $hotel, RoomType $room)
    {
        $this->own($r, $hotel); abort_unless($room->hotel_id === $hotel->id, 404);
        if ($room->reservations()->exists()) return back()->withErrors(['room' => 'Rezervasyonu olan oda tipi silinemez; fiyatını veya adedini düzenleyin.']);
        $room->delete();
        $this->invalidateRoomInfoConfirmation($hotel);
        return back()->with('ok', 'Oda tipi silindi.');
    }

    public function storeRule(Request $r, Hotel $hotel, RoomType $room)
    {
        $this->own($r, $hotel); abort_unless($room->hotel_id === $hotel->id, 404);
        $d = $r->validate(['date_from' => 'required|date', 'date_to' => 'required|date|after_or_equal:date_from', 'price' => 'required|numeric|min:1|max:100000']);
        $room->priceRules()->create($d);
        $this->invalidateRoomInfoConfirmation($hotel);
        return back()->with('ok', 'Sezon fiyatı eklendi.');
    }

    public function destroyRule(Request $r, Hotel $hotel, RoomType $room, PriceRule $rule)
    {
        $this->own($r, $hotel); abort_unless($room->hotel_id === $hotel->id && $rule->room_type_id === $room->id, 404);
        $rule->delete();
        $this->invalidateRoomInfoConfirmation($hotel);
        return back()->with('ok', 'Sezon fiyatı silindi.');
    }
}
