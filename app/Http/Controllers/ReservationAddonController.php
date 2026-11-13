<?php
namespace App\Http\Controllers;

use App\Models\HotelExtra;
use App\Models\Reservation;
use App\Models\ReservationAddonOrder;
use App\Support\Bildirim;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReservationAddonController extends Controller
{
    public function store(Request $request, Reservation $reservation)
    {
        abort_unless($reservation->user_id === $request->user()->id, 404);
        $data = $request->validate([
            'request_token' => ['required', 'uuid'],
            'items' => ['required', 'array', 'max:10'],
            'items.*' => ['nullable', 'integer', 'min:0', 'max:20'],
            'customer_note' => ['nullable', 'string', 'max:1000'],
        ]);
        $quantities = collect($data['items'])->mapWithKeys(fn($qty, $id) => [(int)$id => (int)$qty])->filter(fn($qty) => $qty > 0);
        if ($quantities->isEmpty()) throw ValidationException::withMessages(['items' => 'En az bir ürün veya hizmet için adet seçin.']);

        [$order, $duplicate] = DB::transaction(function () use ($request, $reservation, $data, $quantities) {
            $locked = Reservation::query()->lockForUpdate()->findOrFail($reservation->id);
            $duplicate = ReservationAddonOrder::where('request_token', $data['request_token'])->first();
            if ($duplicate) {
                abort_unless($duplicate->reservation_id === $locked->id && $duplicate->customer_id === $request->user()->id, 409);
                return [$duplicate, true];
            }
            if ($locked->status !== 'confirmed' || !$locked->check_out->gt(today())) {
                throw ValidationException::withMessages(['items' => 'Ek hizmet talebi yalnızca devam eden veya tarihi gelmemiş onaylı rezervasyonlarda açılabilir.']);
            }
            $catalog = HotelExtra::query()->where('hotel_id', $locked->hotel_id)->where('active', true)
                ->where('available_after_booking', true)->whereIn('id', $quantities->keys())->lockForUpdate()->get()->keyBy('id');
            if ($catalog->count() !== $quantities->count()) throw ValidationException::withMessages(['items' => 'Katalogdaki bir ürün artık satışta değil. Sayfayı yenileyip tekrar deneyin.']);
            $total = 0.0;
            $lines = [];
            foreach ($quantities as $id => $quantity) {
                $extra = $catalog->get($id);
                $effectiveQuantity = $quantity * ($extra->unit === 'night' ? (int)$locked->nights : 1);
                $lineTotal = round((float)$extra->price * $effectiveQuantity, 2);
                $total += $lineTotal;
                $lines[] = ['hotel_extra_id' => $extra->id, 'name_snapshot' => $extra->name,
                    'category_snapshot' => $extra->category ?: 'service', 'unit_snapshot' => $extra->unit,
                    'unit_price' => $extra->price, 'quantity' => $effectiveQuantity, 'line_total' => $lineTotal];
            }
            if ($total <= 0 || $total > 9999999999.99) throw ValidationException::withMessages(['items' => 'Toplam tutar geçerli aralığın dışında.']);
            $order = ReservationAddonOrder::create([
                'reservation_id' => $locked->id, 'hotel_id' => $locked->hotel_id,
                'customer_id' => $request->user()->id, 'request_token' => $data['request_token'],
                'status' => 'pending', 'payment_status' => 'unpaid', 'amount' => $total,
                'customer_note' => $data['customer_note'] ?? null,
            ]);
            $order->items()->createMany($lines);
            return [$order, false];
        });
        if (!$duplicate) {
            $order->load('items');
            $summary = $order->items->map(fn($item) => $item->name_snapshot . ' × ' . $item->quantity)->implode(', ');
            $message = $reservation->messages()->create(['user_id' => $request->user()->id,
                'body' => 'EK HİZMET TALEBİ #' . $order->id . ': ' . $summary . ' · Tahmini ' . number_format((float)$order->amount, 2, ',', '.') . ' ₺']);
            Bildirim::yeniMesaj($reservation, $message, 'musteri');
        }
        return back()->with('ok', $duplicate ? 'Bu ek hizmet talebi daha önce alınmış; tekrar oluşturulmadı.' : 'Ek hizmet talebiniz işletmeye iletildi. İşletme onayından sonra tutar ve ödeme durumu rezervasyonunuzda görünür.');
    }

    public function decide(Request $request, Reservation $reservation, ReservationAddonOrder $order)
    {
        abort_unless($reservation->hotel->owner_id === $request->user()->id, 403);
        abort_unless($order->reservation_id === $reservation->id && $order->hotel_id === $reservation->hotel_id, 404);
        $data = $request->validate(['action' => ['required', 'in:approve,reject'], 'hotel_note' => ['nullable', 'string', 'max:1000']]);
        $error = DB::transaction(function () use ($request, $reservation, $order, $data) {
            $lockedOrder = ReservationAddonOrder::query()->lockForUpdate()->findOrFail($order->id);
            $lockedReservation = Reservation::query()->lockForUpdate()->findOrFail($reservation->id);
            if ($lockedOrder->status !== 'pending') return 'Bu ek hizmet talebi daha önce yanıtlanmış.';
            if ($data['action'] === 'approve' && ($lockedReservation->status !== 'confirmed' || !$lockedReservation->check_out->gt(today()))) return 'Rezervasyon artık etkin değil; talep onaylanamaz.';
            $attrs = ['decided_by' => $request->user()->id, 'decided_at' => now(), 'hotel_note' => $data['hotel_note'] ?? null];
            if ($data['action'] === 'reject') {
                $lockedOrder->update($attrs + ['status' => 'rejected']);
                return null;
            }
            $entry = \App\Models\HotelFinancialEntry::create([
                'hotel_id' => $lockedOrder->hotel_id, 'reservation_id' => $lockedReservation->id,
                'created_by' => $request->user()->id, 'type' => 'income',
                'category' => 'Ek hizmet #' . $lockedOrder->id,
                'description' => 'Rezervasyon ek hizmeti · ' . $lockedReservation->code . ' · #' . $lockedOrder->id,
                'amount' => $lockedOrder->amount, 'status' => 'pending', 'due_date' => today()->toDateString(),
                'notes' => 'Müşteri ek hizmet talebi onaylandı. Ödeme mevcut tahsilat akışında kaydedilecek.',
            ]);
            $lockedOrder->update($attrs + ['status' => 'approved', 'payment_status' => 'unpaid', 'financial_entry_id' => $entry->id]);
            return null;
        });
        if ($error) return back()->withErrors(['addon' => $error]);
        $order->refresh();
        $text = $order->status === 'approved'
            ? 'Ek hizmet talebiniz onaylandı. Tutar: ' . number_format((float)$order->amount, 2, ',', '.') . ' ₺. Ödeme kaydı işletme panelinizde bekliyor.'
            : 'Ek hizmet talebiniz işletme tarafından kabul edilmedi.';
        $message = $reservation->messages()->create(['user_id' => $request->user()->id, 'body' => 'EK HİZMET #' . $order->id . ': ' . $text . (($data['hotel_note'] ?? '') ? ' Not: ' . trim($data['hotel_note']) : '')]);
        Bildirim::yeniMesaj($reservation, $message, 'otel');
        return back()->with('ok', $text);
    }

    public function complete(Request $request, Reservation $reservation, ReservationAddonOrder $order)
    {
        abort_unless($reservation->hotel->owner_id === $request->user()->id, 403);
        abort_unless($order->reservation_id === $reservation->id, 404);
        $result = DB::transaction(function () use ($reservation, $order) {
            $locked = ReservationAddonOrder::query()->lockForUpdate()->findOrFail($order->id);
            if ($locked->reservation_id !== $reservation->id) abort(404);
            if ($locked->status === 'completed') return 'already';
            if ($locked->status !== 'approved' || $locked->payment_status !== 'paid') return 'not_ready';
            $locked->update(['status' => 'completed']);
            return 'completed';
        });
        if ($result === 'not_ready') return back()->withErrors(['addon' => 'Tamamlandı olarak işaretlemek için talep onaylanmış ve cari defterde tahsil edilmiş olmalı.']);
        if ($result === 'already') return back()->with('ok', 'Ek hizmet zaten tamamlandı olarak işaretlenmiş.');
        $message = $reservation->messages()->create(['user_id' => $request->user()->id, 'body' => 'EK HİZMET #' . $order->id . ' teslim edildi veya uygulandı.']);
        Bildirim::yeniMesaj($reservation, $message, 'otel');
        return back()->with('ok', 'Ek hizmet tamamlandı olarak işaretlendi.');
    }
}
