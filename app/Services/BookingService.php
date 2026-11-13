<?php
namespace App\Services;

use App\Models\Reservation;
use App\Models\RoomUnit;
use App\Models\RoomType;
use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;

class BookingService
{
    /** Gece sayisina uygun en yuksek aktif indirim yuzdesi (0 = indirim yok). */
    public function discountPercent(\App\Models\Hotel $hotel, int $nights): int
    {
        return (int) \App\Models\HotelDiscount::where('hotel_id', $hotel->id)->where('active', true)
            ->where('min_nights', '<=', $nights)->max('percent');
    }

    /**
     * Rezervasyon teklifi: oda toplami - indirim (yalniz oda uzerinden) + secilen ekstralar.
     * @param \Illuminate\Support\Collection|null $extras secilmis HotelExtra kayitlari
     */
    public function quote(\App\Models\Hotel $hotel, RoomType $room, CarbonInterface $in, CarbonInterface $out, ?\Illuminate\Support\Collection $extras = null, ?\App\Models\GroomingOption $grooming = null, float $insuranceFee = 0, float $pointsDiscount = 0, float $transferFee = 0, float $vipDiscountPercent = 0, float $vipPlatformSharePercent = 0, string $vipTier = 'bronze'): array
    {
        $nights = (int) $in->diffInDays($out);
        $roomTotal = $this->totalPrice($room, $in, $out);
        $pct = $this->discountPercent($hotel, $nights);
        $discount = round($roomTotal * $pct / 100, 2);
        $vipDiscountPercent = max(0, min(100, $vipDiscountPercent));
        $vipDiscount = round(max(0, $roomTotal - $discount) * $vipDiscountPercent / 100, 2);
        $lines = [];
        $extrasTotal = 0.0;
        foreach (($extras ?? collect()) as $e) {
            $qty = $e->unit === 'night' ? $nights : 1;
            $line = round((float) $e->price * $qty, 2);
            $extrasTotal += $line;
            $lines[] = ['hotel_extra_id' => $e->id, 'name' => $e->name, 'unit' => $e->unit, 'price' => (float) $e->price, 'quantity' => $qty, 'total' => $line];
        }
        $gross = round(max(0, $roomTotal - $discount + $extrasTotal + (float)($grooming?->price ?? 0) + $insuranceFee + $transferFee - $pointsDiscount - $vipDiscount), 2);
        $commissionRate = max(0, min(100, (float) config('booking.platform_commission_percentage', 15)));
        $transferRate = max(0, min(100, (float) config('booking_addons.transfer_commission_percent', 0)));
        $commissionCapacity = max(0, ($gross + $vipDiscount - $insuranceFee - $transferFee) * $commissionRate / 100 + $transferFee * $transferRate / 100);
        $platformDiscount = min($commissionCapacity, round($vipDiscount * max(0, min(100, $vipPlatformSharePercent)) / 100, 2));
        return [
            'nights' => $nights, 'room_total' => $roomTotal, 'discount_percent' => $pct, 'discount_total' => $discount,
            'vip_tier' => $vipTier, 'vip_discount_percent' => $vipDiscountPercent,
            'vip_discount_total' => $vipDiscount, 'vip_platform_discount_total' => $platformDiscount,
            'vip_hotel_discount_total' => round($vipDiscount - $platformDiscount, 2),
            'extras_total' => round($extrasTotal, 2), 'extra_lines' => $lines,
            'grooming_fee' => round((float)($grooming?->price ?? 0),2), 'insurance_fee'=>round($insuranceFee,2), 'points_discount'=>round($pointsDiscount,2), 'transfer_fee'=>round($transferFee,2),
            'total' => $gross,
        ];
    }

    /** Gecelik fiyatların toplamı (price_rules varsa tarihe göre, yoksa base_price). */
    public function totalPrice(RoomType $room, CarbonInterface $in, CarbonInterface $out): float
    {
        $rules = $room->priceRules()->where('date_from', '<=', $out->toDateString())
            ->where('date_to', '>=', $in->toDateString())->get();
        $total = 0.0;
        foreach (CarbonPeriod::create($in, $out->copy()->subDay()) as $night) {
            $rule = $rules->first(fn ($r) => $r->date_from->lte($night) && $r->date_to->gte($night));
            $total += (float) ($rule->price ?? $room->base_price);
        }
        return round($total, 2);
    }

    /**
     * Birden cok oda tipi icin musaitligi sabit sayida sorguyla denetler (liste/arama sayfasi icin).
     * @return array<int,int> musait oda tipi id'leri
     */
    public function availableRoomIds(\Illuminate\Support\Collection $rooms, CarbonInterface $in, CarbonInterface $out): array
    {
        $roomIds = $rooms->pluck('id')->all();
        if ($roomIds === []) return [];

        $availableUnits = RoomUnit::query()
            ->selectRaw('room_type_id, COUNT(*) as available_units')
            ->whereIn('room_type_id', $roomIds)
            ->where('status', 'available')
            ->groupBy('room_type_id')
            ->pluck('available_units', 'room_type_id');

        $busyReservations = Reservation::query()
            ->selectRaw('room_type_id, COUNT(DISTINCT room_unit_id) as assigned_units, SUM(CASE WHEN room_unit_id IS NULL THEN 1 ELSE 0 END) as legacy_units')
            ->whereIn('room_type_id', $roomIds)
            ->whereIn('status', Reservation::BLOCKING)
            ->where('check_in', '<', $out->toDateTimeString())
            ->where('check_out', '>', $in->toDateTimeString())
            ->groupBy('room_type_id')
            ->get()
            ->keyBy('room_type_id');

        return $rooms->filter(function ($room) use ($availableUnits, $busyReservations) {
            $busy = $busyReservations->get($room->id);
            $occupiedUnits = (int) ($busy->assigned_units ?? 0) + (int) ($busy->legacy_units ?? 0);

            return (int) $availableUnits->get($room->id, 0) > $occupiedUnits;
        })
            ->pluck('id')->map(fn($id)=>(int)$id)->all();
    }

    /** Choose a saleable physical room for the full stay; legacy reservations without assignments consume a free unit. */
    public function availableUnit(RoomType $room, CarbonInterface $in, CarbonInterface $out, ?int $ignoreId=null): ?\App\Models\RoomUnit
    {
        $units=$room->inventoryUnits()->where('status','available')->orderBy('id')->get();
        if($units->isEmpty()) return null;
        $busy=Reservation::where('room_type_id',$room->id)->whereIn('status',Reservation::BLOCKING)
            ->where('check_in','<',$out->toDateTimeString())->where('check_out','>',$in->toDateTimeString())
            ->when($ignoreId,fn($q)=>$q->where('id','!=',$ignoreId))->get(['room_unit_id']);
        $assigned=$busy->pluck('room_unit_id')->filter()->map(fn($id)=>(int)$id)->unique()->all();
        $legacy=$busy->whereNull('room_unit_id')->count();
        foreach($units as $unit) {
            if(in_array((int)$unit->id,$assigned,true)) continue;
            if($legacy>0) { $legacy--; continue; }
            return $unit;
        }
        return null;
    }

    public function isAvailable(RoomType $room, CarbonInterface $in, CarbonInterface $out, ?int $ignoreId=null): bool
    {
        return $this->availableUnit($room,$in,$out,$ignoreId)!==null;
    }
}
