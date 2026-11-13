<?php
namespace App\Http\Controllers;

use App\Models\Hotel;
use App\Models\Reservation;
use App\Models\ReservationUpdate;
use App\Models\ReservationPaymentAttempt;
use App\Models\ReservationExtension;
use App\Services\BookingService;
use App\Services\HotelGuestProfileService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Hash;

class ReservationController extends Controller
{
    public function index(Request $r)
    {
        $user = $r->user();
        $statusCounts = $user->reservations()
            ->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');
        $list = $user->reservations()->with(['hotel', 'room', 'pet', 'review', 'extras']);
        if (array_key_exists((string) $r->query('durum'), Reservation::STATUS)) {
            $list->where('status', $r->query('durum'));
        }
        $reservations = $list->latest()->paginate(10)->withQueryString();
        return view('reservations.index', [
            'reservations' => $reservations,
            'statuses' => Reservation::STATUS,
            'statusCounts' => $statusCounts,
        ]);
    }

    public function show(Request $r, Reservation $reservation)
    {
        abort_unless($reservation->user_id === $r->user()->id, 403);
        $reservation->load(['hotel.district', 'room', 'pet', 'invoice', 'review', 'extras', 'messages.user', 'records', 'updates.createdBy', 'guestReview.reviewer', 'extensions.financialEntry', 'reservationTransfer', 'groomingOption', 'hotel.extras' => fn($q) => $q->where('active', true)->where('available_after_booking', true), 'waivers', 'addonOrders.items', 'addonOrders.financialEntry', 'calls.recording']);
        return view('reservations.show', ['res' => $reservation]);
    }

    public function transferLocation(Request $r, Reservation $reservation)
    {
        abort_unless($reservation->user_id===$r->user()->id || $reservation->hotel->owner_id===$r->user()->id,404);
        $fresh=$reservation->fresh();
        $active=$fresh->transfer_status==='in_progress' && $fresh->transfer_location_shared_at && $fresh->transfer_location_shared_at->gt(now()->subMinutes(2));
        return response()->json(['active'=>(bool)$active,'lat'=>$active?$fresh->transfer_lat:null,'lng'=>$active?$fresh->transfer_lng:null,'updated_at'=>$active?$fresh->transfer_location_shared_at->format('H:i:s'):null])->header('Cache-Control','no-store');
    }

    public function liveCamera(Request $r, Reservation $reservation, \App\Services\CameraAccessService $cameraAccess)
    {
        abort_unless($reservation->user_id === $r->user()->id, 404);
        abort_unless($cameraAccess->available($reservation), 404);
        $hotel = $reservation->hotel;
        abort_unless(\App\Support\PlatformModules::enabled('live_camera', (int) $hotel->id) && $hotel->camera_enabled && $hotel->camera_embed_url, 404);
        $access = $reservation->cameraAccess;
        $authorizedVersion = $r->session()->get('camera_access.' . $reservation->id);
        $authorized = $access?->enabled && $access->access_code_hash && (int) $authorizedVersion === (int) $access->access_version;
        return response()->view('reservations.camera', [
            'res' => $reservation->load('hotel'),
            'cameraUrl' => $authorized ? $hotel->camera_embed_url : null,
            'cameraEnabled' => (bool) $access?->enabled,
            'authorized' => (bool) $authorized,
        ])->header('Cache-Control', 'private, no-store, max-age=0')->header('Pragma', 'no-cache');
    }

    public function verifyCameraAccess(Request $request, Reservation $reservation, \App\Services\CameraAccessService $cameraAccess)
    {
        abort_unless($reservation->user_id === $request->user()->id, 404);
        abort_unless($cameraAccess->available($reservation), 404);
        abort_unless(\App\Support\PlatformModules::enabled('live_camera', (int) $reservation->hotel_id) && $reservation->hotel->camera_enabled, 404);
        $data = $request->validate(['access_code' => ['required', 'string', 'max:40']]);
        $access = $reservation->cameraAccess;
        if (!$access?->enabled || !$access->access_code_hash || !Hash::check(trim($data['access_code']), $access->access_code_hash)) {
            return back()->withErrors(['access_code' => 'Erişim kodu geçersiz veya yayın işletme tarafından durdurulmuş.']);
        }
        $request->session()->put('camera_access.' . $reservation->id, $access->access_version);
        return redirect()->route('reservations.camera', $reservation)->with('ok', 'Güvenli kamera erişimi açıldı.');
    }

    public function store(Request $r, string $slug, BookingService $svc, HotelGuestProfileService $guestProfiles, \App\Services\PetTransferPricingService $transferPricing)
    {
        $hotel = Hotel::active()->where('slug', $slug)->firstOrFail();
        $d = $r->validate([
            'room_type_id' => 'required|integer',
            'pet_id' => 'required|integer',
            'check_in' => 'required|date|after_or_equal:today',
            'check_out' => 'required|date|after:check_in',
            'customer_note' => 'nullable|string|max:1000',
            'terms' => 'required|boolean',
            'whatsapp_consent' => 'nullable|boolean',
            'extras' => 'nullable|array|max:10',
            'extras.*' => 'integer',
            'grooming_option_id'=>'nullable|integer', 'has_insurance'=>'nullable|boolean',
            'points_to_use'=>'nullable|integer|min:0|max:1000000',
            'transfer_requested'=>'nullable|boolean','transfer_type'=>'nullable|in:one_way,one_way_pickup,one_way_dropoff,round_trip',
            'transfer_fee_accepted'=>'nullable|boolean',
            'transfer_quote_token'=>'required_if:transfer_requested,1|string|max:2048',
            'transfer_pickup_address'=>'required_if:transfer_requested,1|nullable|string|max:500',
            'transfer_return_address'=>'required_if:transfer_type,round_trip|nullable|string|max:500',
            'transfer_pickup_lat'=>'nullable|numeric|between:-90,90','transfer_pickup_lng'=>'nullable|numeric|between:-180,180',
            'transfer_return_lat'=>'nullable|numeric|between:-90,90','transfer_return_lng'=>'nullable|numeric|between:-180,180',
            'transfer_estimated_km'=>'nullable|numeric|min:0.1|max:300',
            'transfer_pickup_at'=>'required_if:transfer_requested,1|nullable|date|after:now',
            'transfer_return_at'=>'required_if:transfer_type,round_trip|nullable|date|after:transfer_pickup_at',
            'transfer_vehicle_type'=>'required_if:transfer_requested,1|nullable|in:sedan,minivan,van',
        ], ['check_in.after_or_equal' => 'Giriş tarihi bugünden önce olamaz.', 'check_out.after' => 'Çıkış tarihi girişten sonra olmalı.']);

        $room = $hotel->rooms()->findOrFail($d['room_type_id']);
        $pet = $r->user()->pets()->findOrFail($d['pet_id']);
        $in = Carbon::parse($d['check_in'])->startOfDay();
        $out = Carbon::parse($d['check_out'])->startOfDay();
        $nights = (int) $in->diffInDays($out);
        $extras = $hotel->extras()->where('active', true)->whereIn('id', array_map('intval', (array) ($d['extras'] ?? [])))->get();
        $transfer = $r->boolean('transfer_requested');
        if ($transfer && !$hotel->transfer_enabled) return back()->withInput()->withErrors(['transfer_requested'=>'Bu tesis transfer sunmuyor.']);
        $transferQuote = $transfer ? $transferPricing->quote($hotel, (string)$d['transfer_pickup_address'], $d['transfer_type'] ?? 'one_way_pickup', $d['transfer_return_address'] ?? null) : ['distance_km'=>null,'fee'=>0.0,'pricing_source'=>'none'];
        if ($transfer) {
            try {
                $signedQuote = json_decode(\Illuminate\Support\Facades\Crypt::decryptString((string)$d['transfer_quote_token']), true, 8, JSON_THROW_ON_ERROR);
                $expectedType = ($d['transfer_type'] ?? 'one_way_pickup') === 'one_way' ? 'one_way_pickup' : ($d['transfer_type'] ?? 'one_way_pickup');
                $validQuote = (int)($signedQuote['hotel_id'] ?? 0) === (int)$hotel->id
                    && ($signedQuote['type'] ?? null) === $expectedType
                    && hash_equals((string)($signedQuote['address_hash'] ?? ''), hash('sha256', mb_strtolower(trim((string)$d['transfer_pickup_address']))))
                    && hash_equals((string)($signedQuote['return_hash'] ?? ''), hash('sha256', mb_strtolower(trim((string)($d['transfer_return_address'] ?? '')))))
                    && (int)($signedQuote['expires_at'] ?? 0) >= now()->timestamp
                    && (int)($signedQuote['fee_cents'] ?? -1) === (int)round((float)$transferQuote['fee'] * 100);
                if (!$validQuote) return back()->withInput()->withErrors(['transfer_requested'=>'Transfer adresi veya ücreti değişti. Lütfen güncel ücreti yeniden hesaplayıp kabul edin.']);
            } catch (\Throwable) {
                return back()->withInput()->withErrors(['transfer_requested'=>'Transfer fiyat onayı geçersiz veya süresi dolmuş. Ücreti yeniden hesaplayın.']);
            }
        }
        $transferKm = $transferQuote['distance_km'];
        $transferTotal = $transferQuote['fee'];
        $grooming = !empty($d['grooming_option_id']) ? \App\Models\GroomingOption::where('is_active',true)->whereKey($d['grooming_option_id'])->where(fn($q)=>$q->whereNull('hotel_id')->orWhere('hotel_id',$hotel->id))->firstOrFail() : null;
        if ($grooming && !in_array($grooming->pet_type,['both',$pet->species],true)) return back()->withInput()->withErrors(['grooming_option_id'=>'Seçilen kuaför hizmeti bu hayvan türüne uygun değil.']);
        $hasInsurance = $r->boolean('has_insurance');
        if ($hasInsurance && (!config('booking_addons.insurance_enabled') || (float)config('booking_addons.daily_insurance_rate',0)<=0 || blank(config('booking_addons.insurance_provider_name')) || blank(config('booking_addons.insurance_terms')))) return back()->withInput()->withErrors(['has_insurance'=>'Sigorta seçeneği şu anda kullanılamıyor.']);
        $insuranceFee = $hasInsurance ? round($nights*(float)config('booking_addons.daily_insurance_rate',0),2) : 0.0;
        $vipTier = app(\App\Services\VipTierService::class)->forUser($r->user());
        $vipPlatformShare = (float) config('booking_addons.vip_platform_share_percent', 0);
        $baseQuote = $svc->quote($hotel,$room,$in,$out,$extras,$grooming,$insuranceFee,0,$transferTotal,$vipTier['discount_percent'],$vipPlatformShare,$vipTier['key']);
        $pointsRequested = (int)($d['points_to_use']??0);
        $pointsDiscount = min((float)$pointsRequested,(float)$r->user()->reward_points,max(0,(float)$baseQuote['total']-1));
        $pointsRedeemed = (int)floor($pointsDiscount);
        $pointsDiscount = (float)$pointsRedeemed;

        if ($nights > 60) return back()->withInput()->withErrors(['check_out' => 'En fazla 60 gece rezervasyon yapılabilir.']);
        if ($room->pet_type !== $pet->species) return back()->withInput()->withErrors(['pet_id' => 'Bu oda tipi seçtiğiniz hayvan türüne uygun değil.']);
        if ($pet->species === 'dog' && $room->size !== 'any' && $room->size !== $pet->size) return back()->withInput()->withErrors(['pet_id' => 'Bu oda tipi seçtiğiniz köpeğin boyuna uygun değil.']);

        $res = DB::transaction(function () use ($svc, $guestProfiles, $room, $hotel, $pet, $r, $in, $out, $nights, $d, $extras, $transfer, $transferKm, $transferTotal, $transferPricing, $grooming, $insuranceFee, $hasInsurance, $pointsDiscount, $pointsRedeemed, $vipTier, $vipPlatformShare) {
            // Aynı evcil hayvan ve fiziksel oda için eşzamanlı çakışmaları sıraya sok.
            \App\Models\Pet::whereKey($pet->id)->lockForUpdate()->first();
            if (Reservation::where('pet_id',$pet->id)->whereIn('status',Reservation::BLOCKING)
                ->where('check_in','<',$out->toDateTimeString())->where('check_out','>',$in->toDateTimeString())->exists()) return 'duplicate';
            \App\Models\RoomType::whereKey($room->id)->lockForUpdate()->first();
            if (!$svc->isAvailable($room, $in, $out)) return null;
            $assignedUnit=$svc->availableUnit($room,$in,$out);
            if(!$assignedUnit) return null;
            $teklif = $svc->quote($hotel, $room, $in, $out, $extras, $grooming, $insuranceFee, $pointsDiscount, $transferTotal, $vipTier['discount_percent'], $vipPlatformShare, $vipTier['key']);
            $kayit = Reservation::create([
                'code' => Reservation::makeCode(), 'user_id' => $r->user()->id, 'hotel_id' => $hotel->id,
                'room_type_id' => $room->id, 'room_unit_id'=>$assignedUnit->id, 'pet_id' => $pet->id, 'check_in' => $in, 'check_out' => $out,
                'nights'=>$nights,'total_price'=>$teklif['total'],'transfer_total'=>$transferTotal,
                'grooming_option_id'=>$grooming?->id,'grooming_fee'=>$grooming?(float)$grooming->price:0,
                'has_insurance'=>$hasInsurance,'insurance_fee'=>$insuranceFee,'insurance_provider_amount'=>$insuranceFee,
                'points_redeemed'=>$pointsRedeemed,'points_discount'=>$pointsDiscount,
                'vip_tier'=>$vipTier['key'],'vip_discount_total'=>$teklif['vip_discount_total'],'vip_platform_discount_total'=>$teklif['vip_platform_discount_total'],
                'transfer_status'=>$transfer?'requested':'not_requested','transfer_type'=>$transfer?($d['transfer_type']??'one_way'):null,
                'transfer_pickup_address'=>$transfer?($d['transfer_pickup_address']??null):null,
                'transfer_return_address'=>$transfer&&($d['transfer_type']??'')==='round_trip'?($d['transfer_return_address']??null):null,
                'transfer_pickup_lat'=>$transfer?($d['transfer_pickup_lat']??null):null,'transfer_pickup_lng'=>$transfer?($d['transfer_pickup_lng']??null):null,
                'transfer_return_lat'=>$transfer?($d['transfer_return_lat']??null):null,'transfer_return_lng'=>$transfer?($d['transfer_return_lng']??null):null,
                'transfer_estimated_km'=>$transfer?$transferKm:null,
                'discount_total' => $teklif['discount_total'], 'extras_total' => $teklif['extras_total'],
                'customer_note' => $d['customer_note'] ?? null,
                'whatsapp_consent_at' => !empty($d['whatsapp_consent']) ? now() : null,
            ]);
            $kayit->setRelation('user', $r->user());
            $guestProfiles->recordReservation($kayit);
            foreach ($teklif['extra_lines'] as $satir) $kayit->extras()->create($satir);
            if ($transfer) {
                $requestedTransferType = $d['transfer_type'] ?? 'one_way_pickup';
                $normalizedTransferType = $requestedTransferType === 'one_way' ? 'one_way_pickup' : $requestedTransferType;
                $isDropoff = $normalizedTransferType === 'one_way_dropoff';
                $kayit->reservationTransfer()->create([
                    'transfer_type' => in_array($normalizedTransferType, ['one_way_pickup','one_way_dropoff','round_trip'], true) ? $normalizedTransferType : 'one_way_pickup',
                    'pickup_address' => $isDropoff ? (string)$hotel->address : (string)$d['transfer_pickup_address'],
                    'dropoff_address' => $normalizedTransferType === 'round_trip' ? (string)$d['transfer_return_address'] : ($isDropoff ? (string)$d['transfer_pickup_address'] : (string)$hotel->address),
                    'pickup_datetime' => $isDropoff ? null : ($d['transfer_pickup_at'] ?? null),
                    'dropoff_datetime' => $isDropoff ? ($d['transfer_pickup_at'] ?? null) : ($normalizedTransferType === 'round_trip' ? ($d['transfer_return_at'] ?? null) : null),
                    'distance_km' => $transferKm,
                    'vehicle_type' => $d['transfer_vehicle_type'] ?? null,
                    'transfer_fee' => $transferTotal,
                    'status' => 'pending',
                ]);
                $petTransferType = $d['transfer_type'] ?? 'one_way_pickup';
                $petTransferDropoff = $petTransferType === 'one_way_dropoff';
                $kayit->petTransfer()->create([
                    'hotel_id'=>$hotel->id,
                    'pickup_address'=>$petTransferDropoff ? (string)$hotel->address : (string)$d['transfer_pickup_address'],
                    'dropoff_address'=>$petTransferType === 'round_trip' ? (string)$d['transfer_return_address'] : ($petTransferDropoff ? (string)$d['transfer_pickup_address'] : (string)$hotel->address),
                    'pickup_at'=>$d['transfer_pickup_at'],
                    'vehicle_type'=>$d['transfer_vehicle_type'],
                    'distance_km'=>$transferKm, 'fee'=>$transferTotal, 'status'=>'requested',
                ]);
            }
            if ($pointsRedeemed > 0) {
                $lockedUser=\App\Models\User::whereKey($r->user()->id)->lockForUpdate()->firstOrFail();
                if ((int)$lockedUser->reward_points < $pointsRedeemed) throw \Illuminate\Validation\ValidationException::withMessages(['points_to_use'=>'PotiPuan bakiyeniz değişti; tutarı yenileyip tekrar deneyin.']);
                $lockedUser->decrement('reward_points',$pointsRedeemed);
                \App\Models\PointTransaction::create(['user_id'=>$lockedUser->id,'reservation_id'=>$kayit->id,'points'=>-$pointsRedeemed,'type'=>'redeemed','description'=>'Rezervasyon indiriminde kullanılan PotiPuan']);
            }
            return $kayit;
        });
        if ($res === 'duplicate') return back()->withInput()->withErrors(['pet_id'=>'Bu dost için belirtilen tarihlerde zaten bekleyen veya onaylı bir rezervasyon var.']);
        if (!$res) return back()->withInput()->withErrors(['check_in' => 'Seçtiğiniz tarihlerde bu oda tipi dolu veya satışa kapalı. Başka tarih veya oda deneyin.']);

        $today = today()->toDateString();
        $label = mb_substr($hotel->slug, 0, 191);
        \Illuminate\Support\Facades\DB::table('site_analytics_daily')->insertOrIgnore([
            'stat_date' => $today, 'dimension' => 'booking_request', 'label' => $label,
            'page_views' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);
        \Illuminate\Support\Facades\DB::table('site_analytics_daily')->where('stat_date', $today)->where('dimension', 'booking_request')->where('label', $label)->increment('page_views');
        \App\Support\Bildirim::rezervasyonAlindi($res);
        \App\Support\WhatsAppCloudApi::queueReservationNotifications((int) $res->id, 'requested');

        return redirect()->route('reservations.show', $res)->with('ok', 'Rezervasyon talebiniz alındı. Otel onayladığında durum güncellenecek.');
    }

    /** Rezervasyon formu icin anlik fiyat teklifi (JSON). Kayit olusturmaz. */
    public function quote(Request $r, string $slug, BookingService $svc, \App\Services\PetTransferPricingService $transferPricing)
    {
        $hotel = Hotel::active()->where('slug', $slug)->firstOrFail();
        $yanit = fn (array $d) => response()->json($d)->header('Cache-Control', 'no-store');
        try {
            $in = Carbon::parse((string) $r->query('check_in'))->startOfDay();
            $out = Carbon::parse((string) $r->query('check_out'))->startOfDay();
        } catch (\Throwable) {
            return $yanit(['ok' => false, 'message' => '']);
        }
        if ($in->lt(today()) || $out->lte($in)) return $yanit(['ok' => false, 'message' => 'Çıkış tarihi giriş tarihinden sonra olmalı.']);
        if ($in->diffInDays($out) > 60) return $yanit(['ok' => false, 'message' => 'En fazla 60 gece seçilebilir.']);
        $room = $hotel->rooms()->find((int) $r->query('room_type_id'));
        if (!$room) return $yanit(['ok' => false, 'message' => 'Oda tipi seçin.']);
        $extras = $hotel->extras()->where('active', true)->whereIn('id', array_map('intval', (array) $r->query('extras', [])))->get();
        $pet = $r->user()?->pets()->find((int)$r->query('pet_id'));
        $grooming = $r->filled('grooming_option_id') ? \App\Models\GroomingOption::where('is_active',true)->whereKey((int)$r->query('grooming_option_id'))->where(fn($x)=>$x->whereNull('hotel_id')->orWhere('hotel_id',$hotel->id))->first() : null;
        $insuranceFee = $r->boolean('has_insurance') && config('booking_addons.insurance_enabled') && (float)config('booking_addons.daily_insurance_rate',0)>0 && config('booking_addons.insurance_provider_name') && config('booking_addons.insurance_terms') ? round($in->diffInDays($out)*(float)config('booking_addons.daily_insurance_rate',0),2) : 0;
        $transferQuote = ['fee'=>0.0,'distance_km'=>null,'pricing_source'=>'none'];
        if ($r->boolean('transfer_requested') && $r->filled('transfer_pickup_address')) {
            try {
                $transferQuote = $transferPricing->quote(
                    $hotel, (string)$r->query('transfer_pickup_address'),
                    $r->query('transfer_type') === 'round_trip' ? 'round_trip' : ($r->query('transfer_type') === 'one_way_dropoff' ? 'one_way_dropoff' : 'one_way_pickup'),
                    $r->query('transfer_return_address')
                );
            } catch (\Illuminate\Validation\ValidationException $e) {
                return $yanit(['ok'=>false,'message'=>collect($e->errors())->flatten()->first()]);
            } catch (\Throwable) {
                return $yanit(['ok'=>false,'message'=>'Transfer ücreti hesaplanamadı.']);
            }
        }
        $vipTier = app(\App\Services\VipTierService::class)->forUser($r->user());
        $vipPlatformShare = (float) config('booking_addons.vip_platform_share_percent', 0);
        $q = $svc->quote($hotel, $room, $in, $out, $extras, $grooming, $insuranceFee, 0, (float)$transferQuote['fee'], $vipTier['discount_percent'], $vipPlatformShare, $vipTier['key']);
        $pointsDiscount = min((float)max(0,(int)$r->query('points_to_use',0)),(float)($r->user()?->reward_points ?? 0),max(0,(float)$q['total']-1));
        $q = $svc->quote($hotel, $room, $in, $out, $extras, $grooming, $insuranceFee, $pointsDiscount, (float)$transferQuote['fee'], $vipTier['discount_percent'], $vipPlatformShare, $vipTier['key']);
        return $yanit(['ok' => true, 'nights' => $q['nights'], 'room_total' => $q['room_total'], 'discount_percent' => $q['discount_percent'],
            'discount_total' => $q['discount_total'], 'vip_tier'=>$vipTier['label'], 'vip_discount_percent'=>$q['vip_discount_percent'], 'vip_discount_total'=>$q['vip_discount_total'], 'vip_hotel_discount_total'=>$q['vip_hotel_discount_total'], 'vip_platform_discount_total'=>$q['vip_platform_discount_total'], 'extras_total' => $q['extras_total'], 'grooming_fee'=>$q['grooming_fee'],'insurance_fee'=>$q['insurance_fee'], 'transfer_total'=>$transferQuote['fee'], 'transfer_distance_km'=>$transferQuote['distance_km'], 'points_discount'=>$pointsDiscount, 'total' => $q['total'],
            'available' => $svc->isAvailable($room, $in, $out)]);
    }

    public function requestEarlyDeparture(Request $r, Reservation $reservation)
    {
        abort_unless($reservation->user_id === $r->user()->id, 404);
        $d = $r->validate(['early_departure_date' => 'required|date|after_or_equal:today', 'early_departure_note' => 'nullable|string|max:1000']);
        $reservation->refresh();
        if ($reservation->status !== 'confirmed' || $reservation->check_out->lte(today())) {
            return back()->withErrors(['early_departure' => 'Yalnızca devam eden veya tarihi gelmemiş onaylı rezervasyon için talep oluşturabilirsiniz.']);
        }
        $newDate = Carbon::parse($d['early_departure_date'])->startOfDay();
        if ($newDate->lte($reservation->check_in) || $newDate->gte($reservation->check_out)) {
            return back()->withInput()->withErrors(['early_departure_date' => 'Yeni çıkış tarihi girişten sonra ve mevcut çıkıştan önce olmalı.']);
        }
        if ($reservation->early_departure_status === 'requested') return back()->withErrors(['early_departure' => 'Bir erken çıkış talebiniz zaten işletme yanıtını bekliyor.']);
        $reservation->update(['early_departure_status' => 'requested', 'early_departure_date' => $newDate->toDateString(), 'early_departure_note' => $d['early_departure_note'] ?? null, 'early_departure_requested_at' => now(), 'early_departure_decided_at' => null]);
        $msg = $reservation->messages()->create(['user_id' => $r->user()->id, 'body' => 'ERKEN ÇIKIŞ TALEBİ: ' . $newDate->format('d.m.Y') . (($d['early_departure_note'] ?? '') !== '' ? ' · ' . trim($d['early_departure_note']) : '')]);
        \App\Support\Bildirim::yeniMesaj($reservation, $msg, 'musteri');
        return back()->with('ok', 'Erken çıkış talebiniz işletmeye iletildi. Onaylanana kadar rezervasyon tarihiniz değişmez.');
    }

    public function cancel(Request $r, Reservation $reservation)
    {
        abort_unless($reservation->user_id === $r->user()->id, 403);
        $result = DB::transaction(function () use ($reservation) {
            $locked = Reservation::query()->lockForUpdate()->findOrFail($reservation->id);
            if (!$locked->canBeCancelled()) return ['error' => 'Bu rezervasyon iptal edilemez.'];
            if ($locked->transfer_status === 'in_progress') {
                return ['error' => 'Canlı transfer sürerken rezervasyon iptal edilemez. Lütfen önce otelle iletişime geçin.'];
            }
            $attempt = ReservationPaymentAttempt::query()
                ->where('reservation_id', $locked->id)->lockForUpdate()->first();
            if ($attempt
                && in_array($attempt->status, ['initializing', 'pending'], true)
                && $attempt->created_at?->gt(now()->subMinutes(20))) {
                return ['error' => 'Kart ödemeniz sonuçlanana kadar rezervasyon iptal edilemez. Ödeme başarısız olursa tekrar deneyin veya 20 dakika sonra yeniden deneyin.'];
            }
            $locked->update([
                'status' => 'cancelled',
                'transfer_status' => $locked->transfer_status === 'requested' ? 'cancelled' : $locked->transfer_status,
            ]);
            return ['cancelled' => true];
        });
        if (!empty($result['error'])) return back()->withErrors(['res' => $result['error']]);
        $reservation->refresh();
        \App\Support\Bildirim::rezervasyonIptalEdildi($reservation);
        return back()->with('ok', 'Rezervasyon iptal edildi.');
    }


    public function requestExtension(
        Request $request,
        Reservation $reservation,
        \App\Services\ReservationExtensionService $extensionService,
        BookingService $booking
    ) {
        abort_unless($reservation->user_id === $request->user()->id, 403);

        if ($reservation->status !== 'confirmed' || !in_array($reservation->payment_status, ['unpaid', 'paid'], true)
            || !$reservation->check_out->gt(today())) {
            return back()->withErrors(['extension' => 'Uzatma talebi yalnızca devam eden veya tarihi gelmemiş onaylı bir rezervasyon için açılabilir.']);
        }

        $data = $request->validate([
            'requested_check_out' => ['required', 'date_format:Y-m-d', 'after:'.$reservation->check_out->toDateString()],
            'customer_note' => ['nullable', 'string', 'max:1000'],
        ]);
        $newCheckOut = Carbon::parse($data['requested_check_out'])->startOfDay();

        if ($reservation->check_in->diffInDays($newCheckOut) > 60) {
            return back()->withInput()->withErrors(['requested_check_out' => 'Toplam konaklama en fazla 60 gece olabilir.']);
        }
        if ($reservation->extensions()->where('status', 'pending')->exists()) {
            return back()->withErrors(['extension' => 'Bir uzatma talebiniz zaten işletmenin yanıtını bekliyor.']);
        }
        if (!$extensionService->hasCapacity($reservation, $newCheckOut, $booking)) {
            return back()->withInput()->withErrors(['requested_check_out' => 'İstenen ek tarihlerde aynı oda uygun görünmüyor.']);
        }

        $quote = $extensionService->quote($reservation, $newCheckOut, $booking);
        $extension = $reservation->extensions()->create([
            'requested_by' => $request->user()->id,
            'current_check_out' => $reservation->check_out->toDateString(),
            'requested_check_out' => $newCheckOut->toDateString(),
            'customer_note' => $data['customer_note'] ?? null,
            'status' => 'pending',
        ] + $quote);

        \App\Support\Bildirim::uzatmaTalebi($reservation, $extension);

        return back()->with('ok', 'Uzatma talebiniz ve ek ücret tahmini işletmeye iletildi.');
    }

    public function updatePhoto(Request $request, Reservation $reservation, ReservationUpdate $update)
    {
        abort_unless($update->reservation_id === $reservation->id, 404);
        $isCustomer = $reservation->user_id === $request->user()->id;
        $isHotelOwner = $reservation->hotel->owner_id === $request->user()->id;
        abort_unless($isCustomer || $isHotelOwner, 404);
        $path = $update->photo_path ?: $update->video_path;
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return response()->file(Storage::disk('local')->path($path), [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

}
