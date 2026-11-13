<?php
namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Reservation;
use App\Services\BookingService;
use App\Models\HotelGuestProfile;
use Illuminate\Http\Request;

class ReservationController extends Controller
{
    public function index(Request $r)
    {
        $hotelIds = $r->user()->hotels()->pluck('id');
        $stayingCount = Reservation::whereIn('hotel_id', $hotelIds)->where('status', 'confirmed')->whereDate('check_in', '<=', today())->whereDate('check_out', '>', today())->count();
        $checkedOutCount = Reservation::whereIn('hotel_id', $hotelIds)->where('status', 'completed')->count();
        $q = Reservation::whereIn('hotel_id', $hotelIds)->with(['hotel', 'room', 'roomUnit', 'pet', 'user', 'extras', 'invoice']);
        if ($r->query('konaklama') === 'konaklayan') $q->where('status', 'confirmed')->whereDate('check_in', '<=', today())->whereDate('check_out', '>', today());
        elseif ($r->query('konaklama') === 'cikis-yapan') $q->where('status', 'completed');
        if ($r->filled('durum') && array_key_exists($r->durum, Reservation::STATUS)) $q->where('status', $r->durum);
        return view('panel.reservations', ['reservations' => $q->latest()->paginate(15)->withQueryString(), 'stayingCount' => $stayingCount, 'checkedOutCount' => $checkedOutCount]);
    }

    public function guests(Request $r)
    {
        $hotelIds = $r->user()->hotels()->pluck('id')->filter(fn ($id) => \App\Support\PlatformModules::enabled('guest_crm', (int) $id))->values();
        // New partners and hotels without enabled CRM should see an onboarding empty state, not a 404.
        $hasHotels = $hotelIds->isNotEmpty();
        $search = trim((string) $r->query('q', ''));
        $query = HotelGuestProfile::query()->whereIn('hotel_id', $hotelIds)->with([
            'hotel:id,name', 'user:id,name,email,phone,address',
        ]);
        if ($search !== '') {
            $query->where(function($q) use ($search) {
                $q->where('guest_name', 'like', "%{$search}%")
                    ->orWhere('guest_email', 'like', "%{$search}%")
                    ->orWhere('guest_phone', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%")
                    ->orWhereHas('hotel', fn($hotel) => $hotel->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('user', fn($user) => $user->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('address', 'like', "%{$search}%"));
            });
        }
        $guests = $query->orderByDesc('last_seen_at')->orderBy('guest_name')->paginate(20)->withQueryString();

        $reservations = collect();
        if ($guests->isNotEmpty()) {
            $reservations = Reservation::query()->where(function ($pairs) use ($guests) {
                foreach ($guests as $profile) {
                    $pairs->orWhere(fn($pair) => $pair->where('hotel_id', $profile->hotel_id)->where('user_id', $profile->user_id));
                }
            })->with([
                'hotel:id,name', 'room:id,name', 'roomUnit:id,name', 'pet:id,name,species,size', 'extras:id,name',
                'financialEntries' => fn($entries) => $entries->whereIn('status', ['paid', 'pending'])->orderByDesc('created_at'),
            ])->latest('check_in')->get();
        }

        $byGuest = $reservations->groupBy(fn($reservation) => $reservation->hotel_id . ':' . $reservation->user_id);
        foreach ($guests as $profile) {
            $stays = $byGuest->get($profile->hotel_id . ':' . $profile->user_id, collect());
            $profile->setRelation('reservations', $stays);
            $billable = $stays->whereNotIn('status', ['cancelled', 'rejected']);
            $profile->booking_count = $billable->count();
            $profile->completed_stays = $billable->where('status', 'completed')->count();
            $profile->booking_volume = $billable->where('payment_status', '!=', 'refunded')->sum(fn($stay) => (float) $stay->total_price);
            $profile->collected_total = $billable->where('payment_status', '!=', 'refunded')->sum(function ($stay) {
                $paidEntries = $stay->financialEntries->where('type', 'income')->where('status', 'paid');
                return $paidEntries->isNotEmpty() ? $paidEntries->sum(fn($entry) => (float) $entry->amount)
                    : ($stay->payment_status === 'paid' ? (float) $stay->total_price : 0);
            });
            $profile->pending_total = $billable->where('payment_status', '!=', 'refunded')->sum(function ($stay) {
                if ($stay->payment_status !== 'paid') return (float) $stay->total_price;
                return $stay->financialEntries->where('type', 'income')->where('status', 'pending')->sum(fn($entry) => (float) $entry->amount);
            });
            $profile->ledger_entries = $billable->flatMap(fn($stay) => $stay->financialEntries)->sortByDesc('created_at')->take(8)->values();
        }

        return view('panel.guests', compact('guests', 'search', 'hasHotels'));
    }

    public function saveGuestNotes(Request $request, HotelGuestProfile $profile)
    {
        abort_unless($request->user()->hotels()->whereKey($profile->hotel_id)->exists(), 404);
        abort_unless(\App\Support\PlatformModules::enabled('guest_crm', (int) $profile->hotel_id), 404);
        $data = $request->validate(['notes' => ['nullable', 'string', 'max:4000']]);
        $profile->update(['notes' => trim((string) ($data['notes'] ?? '')) ?: null]);
        return back()->with('ok', 'Misafir notu kaydedildi. Bu not yalnızca tesisinizin yetkili panelinde görünür.');
    }

    public function cameraAccess(Request $request, Reservation $reservation)
    {
        $this->own($request, $reservation);
        abort_unless(\App\Support\PlatformModules::enabled('live_camera', (int) $reservation->hotel_id), 404);
        abort_unless($reservation->hotel->camera_enabled && $reservation->hotel->camera_embed_url, 422, 'Önce tesis ayarlarından güvenli HTTPS kamera yayını tanımlayın.');
        abort_unless($reservation->status === 'confirmed' && $reservation->check_in->lte(today()) && $reservation->check_out->gt(today()), 422, 'Kamera erişimi yalnızca devam eden konaklamalarda yönetilebilir.');
        $data = $request->validate(['action' => ['required', 'in:enable,rotate,disable']]);
        $access = $reservation->cameraAccess()->firstOrNew(['reservation_id' => $reservation->id]);

        if ($data['action'] === 'disable') {
            $access->fill(['enabled' => false, 'access_code_hash' => null, 'access_version' => max(1, (int) $access->access_version) + 1, 'changed_by' => $request->user()->id, 'disabled_at' => now()])->save();
            return back()->with('ok', 'Bu misafir için canlı kamera yayını durduruldu. Önceki erişim kodu artık geçersiz.');
        }

        if ($data['action'] === 'enable' && $access->enabled && $access->access_code_hash) {
            return back()->withErrors(['camera' => 'Yayın zaten açık. Kodu yeniden oluşturmak için erişim kodunu yenileyin.']);
        }

        $code = \Illuminate\Support\Str::upper(\Illuminate\Support\Str::random(4)) . '-' . random_int(1000, 9999);
        $access->fill([
            'enabled' => true,
            'access_code_hash' => \Illuminate\Support\Facades\Hash::make($code),
            'access_version' => max(1, (int) $access->access_version) + 1,
            'changed_by' => $request->user()->id,
            'enabled_at' => now(),
            'disabled_at' => null,
        ])->save();

        return back()->with('ok', 'Canlı yayın bu rezervasyon için açıldı. Güvenli erişim kodu müşteriye özel sohbette iletilmelidir.')
            ->with('camera_access_code', $code);
    }

    private function own(Request $r, Reservation $reservation): void
    {
        abort_unless($reservation->hotel->owner_id === $r->user()->id, 403);
    }

    public function show(Request $r, Reservation $reservation)
    {
        $this->own($r, $reservation);
        $reservation->load(['hotel', 'room', 'roomUnit', 'pet.vaccinations', 'pet.medicalDocuments', 'user', 'invoice', 'extras', 'messages.user', 'records', 'updates.createdBy', 'guestReview', 'extensions.requester', 'addonOrders.items', 'addonOrders.customer', 'addonOrders.financialEntry', 'reservationTransfer', 'groomingOption', 'cameraAccess', 'calls.recording']);
        $updateTemplates = \Illuminate\Support\Facades\DB::table('hotel_stay_update_templates')->where('hotel_id', $reservation->hotel_id)->orderBy('title')->get();
        $chatTitle = 'Misafir rezervasyonu ile güvenli mesajlaşma';
        $chatSubtitle = 'Misafir: '.$reservation->user->name.' · Konaklayan pet: '.$reservation->pet->name.' · Oda: '.$reservation->room->name.($reservation->roomUnit ? ' · '.$reservation->roomUnit->name : '').' · Konaklama: '.$reservation->check_in->format('d.m.Y').'–'.$reservation->check_out->format('d.m.Y');
        return view('panel.reservation', ['res'=>$reservation,'updateTemplates'=>$updateTemplates,'chatTitle'=>$chatTitle,'chatSubtitle'=>$chatSubtitle]);
    }

    public function message(Request $r, Reservation $reservation)
    {
        $this->own($r, $reservation);
        $d = $r->validate(['body' => 'required|string|max:1000']);
        $msg = $reservation->messages()->create(['user_id' => $r->user()->id, 'body' => trim($d['body'])]);
        \App\Support\Bildirim::yeniMesaj($reservation, $msg, 'otel');
        if ($r->expectsJson()) return response()->json(['id'=>$msg->id,'sender'=>$reservation->hotel->name,'body'=>$msg->body,'created_at'=>$msg->created_at->format('d.m.Y H:i'),'mine'=>true],201)->header('Cache-Control','no-store');
        return back()->with('ok', 'Mesaj misafire gönderildi.');
    }

    public function poll(Request $r, Reservation $reservation)
    {
        $this->own($r, $reservation);
        $rows = $reservation->messages()->where('id','>',max(0,(int)$r->query('after',0)))->with('user:id,name')->limit(100)->get();
        return response()->json(['messages'=>$rows->map(fn($m)=>['id'=>$m->id,'sender'=>$m->user_id===$r->user()->id?'Siz':$reservation->user->name,'body'=>$m->body,'created_at'=>$m->created_at?->format('d.m.Y H:i'),'mine'=>$m->user_id===$r->user()->id])])->header('Cache-Control','no-store');
    }

    /** Giris/cikis tutanagi: otel hazirlar ve imzalar, misafir onaylar. */
    public function record(Request $r, Reservation $reservation)
    {
        $this->own($r, $reservation);
        $d = $r->validate(['type' => 'required|in:checkin,checkout', 'note' => 'nullable|string|max:1500']);
        if (!in_array($reservation->status, ['confirmed', 'completed'], true)) {
            return back()->withErrors(['res' => 'Tutanak yalnızca onaylı rezervasyonlarda hazırlanır.']);
        }
        if ($d['type'] === 'checkin' && $reservation->check_in->isFuture()) {
            return back()->withErrors(['res' => 'Giriş tutanağı giriş gününden önce hazırlanamaz.']);
        }
        if ($d['type'] === 'checkout' && !$reservation->records()->where('type', 'checkin')->exists()) {
            return back()->withErrors(['res' => 'Önce giriş tutanağı hazırlanmalı.']);
        }
        if ($reservation->records()->where('type', $d['type'])->exists()) {
            return back()->withErrors(['res' => 'Bu tutanak zaten hazırlanmış.']);
        }
        $rec = $reservation->records()->create([
            'type' => $d['type'], 'note' => $d['note'] ?? null, 'created_by' => $r->user()->id, 'hotel_signed_at' => now(),
        ]);
        \App\Support\Bildirim::tutanak($reservation, $rec);
        return back()->with('ok', 'Tutanak hazırlandı; misafirin onayı bekleniyor.');
    }

    public function startTransfer(Request $r, Reservation $reservation)
    {
        $this->own($r,$reservation);
        abort_unless($reservation->transfer_status==='requested' && $reservation->status==='confirmed',409);
        $reservation->update(['transfer_status'=>'in_progress','transfer_started_at'=>now(),'transfer_completed_at'=>null]);
        $reservation->reservationTransfer?->update(['status'=>'confirmed']);
        $msg=$reservation->messages()->create(['user_id'=>$r->user()->id,'body'=>'Pet transfer yolculuğu başladı. Konum paylaşımı bu sayfa açık kaldığı sürece yapılır.']); \App\Support\Bildirim::yeniMesaj($reservation,$msg,'otel');
        return back()->with('ok','Transfer başlatıldı. Konum iznini vererek canlı konum paylaşımını açık tutun.');
    }
    public function completeTransfer(Request $r, Reservation $reservation)
    {
        $this->own($r,$reservation);
        abort_unless($reservation->transfer_status==='in_progress',409);
        $reservation->update(['transfer_status'=>'completed','transfer_completed_at'=>now(),'transfer_lat'=>null,'transfer_lng'=>null,'transfer_location_shared_at'=>null]);
        $reservation->reservationTransfer?->update(['status'=>'completed']);
        $msg=$reservation->messages()->create(['user_id'=>$r->user()->id,'body'=>'Pet transfer tamamlandı.']); \App\Support\Bildirim::yeniMesaj($reservation,$msg,'otel');
        return back()->with('ok','Transfer tamamlandı.');
    }
    public function updateTransferLocation(Request $r, Reservation $reservation)
    {
        $this->own($r,$reservation);
        abort_unless($reservation->transfer_status==='in_progress',409);
        $d=$r->validate(['lat'=>'required|numeric|between:-90,90','lng'=>'required|numeric|between:-180,180']);
        $reservation->update(['transfer_lat'=>$d['lat'],'transfer_lng'=>$d['lng'],'transfer_location_shared_at'=>now()]);
        return response()->json(['ok'=>true])->header('Cache-Control','no-store');
    }

    public function decideEarlyDeparture(Request $r, Reservation $reservation, BookingService $svc)
    {
        $this->own($r, $reservation);
        $d = $r->validate(['decision' => 'required|in:approve,reject', 'hotel_note' => 'nullable|string|max:1000']);
        $body = \Illuminate\Support\Facades\DB::transaction(function () use ($r, $reservation, $d, $svc) {
            $res = Reservation::whereKey($reservation->id)->lockForUpdate()->firstOrFail();
            if ($res->early_departure_status !== 'requested' || !$res->early_departure_date) {
                throw \Illuminate\Validation\ValidationException::withMessages(['early_departure' => 'Bekleyen erken çıkış talebi bulunamadı.']);
            }
            if ($d['decision'] === 'reject') {
                $res->update(['early_departure_status' => 'rejected', 'early_departure_decided_at' => now()]);
                return 'Erken çıkış talebiniz işletme tarafından onaylanmadı.' . (($d['hotel_note'] ?? '') !== '' ? ' Not: ' . trim($d['hotel_note']) : '');
            }
            $pendingExtensionFee = \App\Models\HotelFinancialEntry::query()
                ->where('reservation_id', $res->id)->where('type', 'income')
                ->where('status', 'pending')->where('category', 'like', 'Rezervasyon uzatma #%')->exists();
            if ($res->payment_status === 'paid' && $pendingExtensionFee) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'early_departure' => 'Ödenmiş rezervasyona ait uzatma ek ücreti henüz tahsil edilmedi. İade hesabının doğru olması için önce Cari ekranından tahsilatı kaydedin.',
                ]);
            }

            $newOut = \Illuminate\Support\Carbon::parse($res->early_departure_date)->startOfDay();
            if ($newOut->lte($res->check_in) || $newOut->gte($res->check_out)) {
                throw \Illuminate\Validation\ValidationException::withMessages(['early_departure' => 'Talep tarihi geçersiz; müşteriyle yeni tarih belirleyin.']);
            }
            $newNights = (int) $res->check_in->diffInDays($newOut);
            $roomTotal = $svc->totalPrice($res->room, $res->check_in, $newOut);
            $discount = round($roomTotal * $svc->discountPercent($res->hotel, $newNights) / 100, 2);
            $extraTotal = 0.0;
            foreach ($res->extras as $extra) {
                if ($extra->unit === 'night') {
                    $extra->quantity = $newNights;
                    $extra->total = round((float) $extra->price * $newNights, 2);
                    $extra->save();
                }
                $extraTotal += (float) $extra->total;
            }
            $revisedTotal = round($roomTotal - $discount + $extraTotal + (float) $res->transfer_total, 2);
            $refund = $res->payment_status === 'paid' ? max(0, round((float) $res->total_price - $revisedTotal, 2)) : 0;
            $res->update([
                'check_out' => $newOut->toDateString(), 'nights' => $newNights,
                'early_departure_status' => 'approved', 'early_departure_decided_at' => now(),
                'early_departure_refund_amount' => $refund,
                'total_price' => $res->payment_status === 'paid' ? $res->total_price : $revisedTotal,
                'extras_total' => $extraTotal, 'discount_total' => $discount,
                'hotel_note' => ($d['hotel_note'] ?? '') ?: $res->hotel_note,
            ]);
            if ($refund > 0) {
                \App\Models\HotelFinancialEntry::updateOrCreate(
                    ['reservation_id' => $res->id, 'category' => 'Erken çıkış iadesi'],
                    ['hotel_id' => $res->hotel_id, 'created_by' => $r->user()->id, 'type' => 'expense',
                     'description' => 'Erken çıkış iadesi · ' . $res->code, 'amount' => $refund,
                     'status' => 'pending', 'due_date' => $newOut->toDateString(), 'paid_at' => null,
                     'payment_method' => null, 'notes' => 'Rezervasyon erken çıkış onayı sonrası hesaplanan iade/mahsup. Ödeme yapıldığında cari kayıtta kapatın.']
                );
            }
            $text = 'Erken çıkış talebiniz onaylandı. Rezervasyon çıkış tarihiniz ' . $newOut->format('d.m.Y') . ' olarak güncellendi.';
            if ($refund > 0) $text .= ' Tahmini iade/mahsup farkı ' . number_format($refund, 2, ',', '.') . ' ₺; ödeme iadesi işletmeyle ayrıca tamamlanmalıdır.';
            if (($d['hotel_note'] ?? '') !== '') $text .= ' Not: ' . trim($d['hotel_note']);
            return $text;
        });
        $reservation->refresh();
        $msg = $reservation->messages()->create(['user_id' => $r->user()->id, 'body' => 'ERKEN ÇIKIŞ: ' . $body]);
        \App\Support\Bildirim::yeniMesaj($reservation, $msg, 'otel');
        return back()->with('ok', $body);
    }

    public function update(Request $r, Reservation $reservation, BookingService $svc)
    {
        abort_unless($reservation->hotel->owner_id === $r->user()->id, 403);
        $d = $r->validate(['action' => 'required|in:confirm,reject,complete,mark_paid,mark_unpaid', 'hotel_note' => 'nullable|string|max:1000']);

        $result = \Illuminate\Support\Facades\DB::transaction(function () use ($r, $reservation, $svc, $d) {
            $locked = Reservation::with(['hotel', 'room'])->lockForUpdate()->findOrFail($reservation->id);
            abort_unless($locked->hotel->owner_id === $r->user()->id, 403);
            $status = $locked->status;
            $error = null;

            switch ($d['action']) {
                case 'confirm':
                    if ($status !== 'pending') $error = 'Yalnızca bekleyen talepler onaylanabilir.';
                    elseif (!$svc->isAvailable($locked->room, $locked->check_in, $locked->check_out, $locked->id)) $error = 'Bu tarihlerde oda dolu görünüyor.';
                    else $locked->status = 'confirmed';
                    break;
                case 'reject':
                    if ($status !== 'pending') $error = 'Yalnızca bekleyen talepler reddedilebilir.';
                    else {
                        $locked->status = 'rejected';
                        if ($locked->transfer_status === 'requested') $locked->transfer_status = 'cancelled';
                    }
                    break;
                case 'complete':
                    if ($status !== 'confirmed' || $locked->check_in->isFuture() || $locked->transfer_status === 'in_progress') {
                        $error = 'Konaklama tamamlanmadan ve canlı transfer kapatılmadan rezervasyon tamamlanamaz.';
                    } else {
                        // Konaklama tamamlanması tahsilat kanıtı değildir; ödeme ayrı işlenir.
                        $locked->status = 'completed';
                    }
                    break;
                case 'mark_paid':
                    if (!in_array($status, ['confirmed', 'completed'], true)) $error = 'Ödeme yalnızca onaylı rezervasyonda işlenir.';
                    else $locked->payment_status = 'paid';
                    break;
                case 'mark_unpaid':
                    if (!in_array($status, ['confirmed', 'completed'], true)) $error = 'Ödeme durumu yalnızca onaylı rezervasyonda değiştirilebilir.';
                    elseif (\App\Models\ReservationInvoice::where('reservation_id', $locked->id)->where('status', 'issued')->exists()) $error = 'Resmî fatura düzenlendikten sonra tahsilat buradan geri alınamaz; düzeltme için mali müşavirinize başvurun.';
                    else $locked->payment_status = 'unpaid';
                    break;
            }

            if ($error) return ['error' => $error];
            if (!empty($d['hotel_note'])) $locked->hotel_note = $d['hotel_note'];
            $locked->save();

            if ($d['action'] === 'mark_unpaid') {
                \App\Models\HotelFinancialEntry::where('reservation_id',$locked->id)->where('category','Rezervasyon tahsilatı')->where('status','paid')
                    ->update(['status'=>'cancelled','notes'=>'Tahsilat işletme panelinde geri alındı · '.now()->format('d.m.Y H:i'),'updated_at'=>now()]);
                \App\Models\ReservationInvoice::where('reservation_id',$locked->id)->where('status','pending')->delete();
            }
            if (in_array($d['action'], ['complete', 'mark_paid'], true) && $locked->payment_status === 'paid') {
                \App\Models\HotelFinancialEntry::updateOrCreate(
                    ['reservation_id' => $locked->id, 'category' => 'Rezervasyon tahsilatı'],
                    ['hotel_id' => $locked->hotel_id, 'created_by' => $r->user()->id, 'type' => 'income',
                     'description' => 'Rezervasyon tahsilatı · ' . $locked->code,
                     'amount' => max(0, (float) $locked->total_price - (float) \App\Models\HotelFinancialEntry::where('reservation_id', $locked->id)->where('type', 'income')->whereIn('status', ['pending', 'paid'])->where('category', 'like', 'Rezervasyon uzatma #%')->sum('amount')), 'status' => 'paid',
                     'due_date' => $locked->check_in->toDateString(), 'paid_at' => now(),
                     'payment_method' => 'İşletme tarafından kaydedildi',
                     'notes' => 'Rezervasyon panelinde ödeme alındı olarak işaretlendi.']
                );
            }
            return ['error' => null, 'reservation' => $locked];
        });

        if ($result['error']) return back()->withErrors(['res' => $result['error']]);
        if ($d['action'] === 'confirm') \App\Support\WhatsAppCloudApi::queueReservationNotifications((int) $result['reservation']->id, 'confirmed');
        $reservation = $result['reservation'];
        if ($reservation->payment_status === 'paid') \App\Models\ReservationInvoice::ensurePending($reservation);
        if (in_array($d['action'], ['confirm', 'reject', 'complete'], true)) \App\Support\Bildirim::rezervasyonDurum($reservation);
        return back()->with('ok', 'Rezervasyon güncellendi.');
    }

    public function decideExtension(
        Request $request,
        Reservation $reservation,
        \App\Models\ReservationExtension $extension,
        \App\Services\ReservationExtensionService $extensionService,
        \App\Services\BookingService $booking
    ) {
        $this->own($request, $reservation);
        abort_unless($extension->reservation_id === $reservation->id, 404);
        $data = $request->validate([
            'action' => ['required', 'in:approve,reject'],
            'hotel_note' => ['nullable', 'string', 'max:1000'],
        ]);

        $error = \Illuminate\Support\Facades\DB::transaction(function () use ($request, $reservation, $extension, $extensionService, $booking, $data) {
            $lockedReservation = Reservation::query()->lockForUpdate()->findOrFail($reservation->id);
            $lockedExtension = \App\Models\ReservationExtension::query()->lockForUpdate()->findOrFail($extension->id);

            if ($lockedExtension->status !== 'pending') {
                return 'Bu uzatma talebi daha önce yanıtlanmış.';
            }

            $attributes = [
                'decided_by' => $request->user()->id,
                'decided_at' => now(),
                'hotel_note' => $data['hotel_note'] ?? null,
            ];

            if ($data['action'] === 'reject') {
                $lockedExtension->update($attributes + ['status' => 'rejected']);
                return null;
            }

            if ($lockedReservation->status !== 'confirmed'
                || !in_array($lockedReservation->payment_status, ['unpaid', 'paid'], true)
                || !$lockedReservation->check_out->gt(today())
                || $lockedReservation->check_out->toDateString() !== $lockedExtension->current_check_out->toDateString()) {
                return 'Rezervasyon durumu veya çıkış tarihi değişti; talep güvenle onaylanamaz.';
            }

            if ($lockedReservation->payment_status !== 'paid') {
                $paymentAttempt = \App\Models\ReservationPaymentAttempt::query()
                    ->where('reservation_id', $lockedReservation->id)->lockForUpdate()->first();
                if ($paymentAttempt && in_array($paymentAttempt->status, ['initializing', 'pending'], true)) {
                    return 'Müşterinin rezervasyon kart ödemesi henüz sonuçlanmadı. Önce ödeme sonucunu bekleyin; ardından uzatmayı onaylayın.';
                }
            }

            // Serialize extension approvals with new bookings for this room type.
            \App\Models\RoomType::query()->whereKey($lockedReservation->room_type_id)->lockForUpdate()->firstOrFail();

            if (!$extensionService->hasCapacity($lockedReservation, $lockedExtension->requested_check_out, $booking)) {
                return 'Ek tarihler için oda artık müsait değil.';
            }

            $separateCollection = $lockedReservation->payment_status === 'paid';
            $extensionService->apply($lockedReservation, $lockedExtension);
            $attributes['status'] = 'approved';
            if ($separateCollection) {
                $entry = \App\Models\HotelFinancialEntry::create([
                    'hotel_id' => $lockedReservation->hotel_id,
                    'reservation_id' => $lockedReservation->id,
                    'created_by' => $request->user()->id,
                    'type' => 'income',
                    'category' => 'Rezervasyon uzatma #' . $lockedExtension->id,
                    'description' => 'Rezervasyon uzatma · ' . $lockedReservation->code . ' · ' . $lockedExtension->added_nights . ' gece',
                    'amount' => $lockedExtension->quoted_total,
                    'status' => 'pending',
                    'due_date' => today()->toDateString(),
                    'notes' => 'Uzatma onayında oluşturuldu. Tahsilat işletme panelinden kaydedilir.',
                ]);
                $attributes['hotel_financial_entry_id'] = $entry->id;
            }
            $lockedExtension->update($attributes);
            return null;
        });

        if ($error) {
            return back()->withErrors(['extension' => $error]);
        }

        $extension->refresh();
        \App\Support\Bildirim::uzatmaDurumu($reservation->fresh(), $extension);

        return back()->with('ok', $data['action'] === 'approve'
            ? 'Uzatma onaylandı; yeni çıkış tarihi ve tutar güncellendi.'
            : 'Uzatma talebi reddedildi.');
    }

    public function reviewGuest(Request $request, Reservation $reservation)
    {
        $this->own($request, $reservation);
        abort_unless($reservation->status === 'completed' && !$reservation->check_out->gt(today()), 403);

        if ($reservation->guestReview()->exists()) {
            return back()->withErrors(['guest_review' => 'Bu konaklama için misafir değerlendirmesi zaten gönderildi.']);
        }

        $data = $request->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
            'title' => ['nullable', 'string', 'max:150'],
            'comment' => ['nullable', 'string', 'max:1500'],
        ]);

        $review = $reservation->guestReview()->create([
            'hotel_id' => $reservation->hotel_id,
            'reviewer_id' => $request->user()->id,
            'guest_id' => $reservation->user_id,
            'rating' => $data['rating'],
            'title' => $data['title'] ?? null,
            'comment' => $data['comment'] ?? null,
        ]);

        \App\Support\Bildirim::misafirDegerlendirildi($reservation, $review);

        return back()->with('ok', 'Misafir değerlendirmeniz kaydedildi.');
    }

}
