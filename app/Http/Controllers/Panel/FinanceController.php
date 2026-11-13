<?php
namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Hotel;
use App\Models\HotelFinancialEntry;
use App\Models\Reservation;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use App\Support\Bildirim;

class FinanceController extends Controller
{
    public function index(Request $request)
    {
        $hotels = $request->user()->hotels()->orderBy('name')->get(['id', 'name']);
        $hotelIds = $hotels->pluck('id');
        $toRules = ['nullable', 'date_format:Y-m-d'];
        if ($request->filled('from')) $toRules[] = 'after_or_equal:from';
        $filters = $request->validate([
            'hotel_id' => ['nullable', 'integer', Rule::in($hotelIds->all())],
            'type' => ['nullable', Rule::in(['income', 'expense'])],
            'status' => ['nullable', Rule::in(['pending', 'paid', 'cancelled'])],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => $toRules,
        ]);
        $base = HotelFinancialEntry::query()->whereIn('hotel_id', $hotelIds);
        if (!empty($filters['hotel_id'])) $base->where('hotel_id', $filters['hotel_id']);
        $this->applyDateRange($base, $filters);
        $summary = [
            'income' => (clone $base)->where('type', 'income')->where('status', 'paid')->sum('amount'),
            'pending_income' => (clone $base)->where('type', 'income')->where('status', 'pending')->sum('amount'),
            'expenses' => (clone $base)->where('type', 'expense')->where('status', 'paid')->sum('amount'),
            'pending_expenses' => (clone $base)->where('type', 'expense')->where('status', 'pending')->sum('amount'),
        ];
        $entries = (clone $base)->with(['hotel:id,name', 'reservation:id,code', 'creator:id,name'])
            ->when(!empty($filters['type']), fn($q) => $q->where('type', $filters['type']))
            ->when(!empty($filters['status']), fn($q) => $q->where('status', $filters['status']))
            ->latest()->paginate(25)->withQueryString();
        return view('panel.finance', compact('hotels', 'entries', 'summary', 'filters'));
    }

    public function export(Request $request)
    {
        $hotelIds = $request->user()->hotels()->pluck('id');
        $toRules = ['nullable', 'date_format:Y-m-d'];
        if ($request->filled('from')) $toRules[] = 'after_or_equal:from';
        $filters = $request->validate([
            'hotel_id' => ['nullable', 'integer', Rule::in($hotelIds->all())],
            'type' => ['nullable', Rule::in(['income', 'expense'])],
            'status' => ['nullable', Rule::in(['pending', 'paid', 'cancelled'])],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => $toRules,
        ]);
        $query = HotelFinancialEntry::query()->whereIn('hotel_id', $hotelIds)
            ->with(['hotel:id,name', 'reservation:id,code'])
            ->when(!empty($filters['hotel_id']), fn($q) => $q->where('hotel_id', $filters['hotel_id']))
            ->when(!empty($filters['type']), fn($q) => $q->where('type', $filters['type']))
            ->when(!empty($filters['status']), fn($q) => $q->where('status', $filters['status']));
        $this->applyDateRange($query, $filters);

        return response()->streamDownload(function () use ($query) {
            $stream = fopen('php://output', 'w');
            fwrite($stream, "\xEF\xBB\xBF");
            fputcsv($stream, ['Kayıt tarihi', 'Tahsilat/ödeme tarihi', 'İşletme', 'Rezervasyon kodu', 'Tür', 'Kategori', 'Açıklama', 'Tutar (TRY)', 'Durum', 'Ödeme yöntemi', 'Referans', 'Not'], ';');
            $query->orderByDesc('created_at')->chunk(500, function ($entries) use ($stream) {
                foreach ($entries as $entry) {
                    $safe = static fn($value) => preg_match('/^[=+@\\-\\t\\r]/u', (string) $value) ? "'".$value : $value;
                    fputcsv($stream, [
                        $entry->created_at?->format('Y-m-d'), $entry->paid_at?->format('Y-m-d H:i'),
                        $safe($entry->hotel?->name), $safe($entry->reservation?->code),
                        $entry->type === 'income' ? 'Gelir' : 'Gider', $safe($entry->category),
                        $safe($entry->description), number_format((float) $entry->amount, 2, '.', ''),
                        ['pending'=>'Bekliyor','paid'=>'Ödendi','cancelled'=>'İptal edildi'][$entry->status] ?? $entry->status,
                        $safe($entry->payment_method), $safe($entry->reference), $safe($entry->notes),
                    ], ';');
                }
            });
            fclose($stream);
        }, 'cari-raporu-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function applyDateRange($query, array $filters): void
    {
        if (!empty($filters['from'])) $query->whereDate('created_at', '>=', $filters['from']);
        if (!empty($filters['to'])) $query->whereDate('created_at', '<=', $filters['to']);
    }

    public function store(Request $request)
    {
        $hotelIds = $request->user()->hotels()->pluck('id');
        $data = $request->validate([
            'hotel_id' => ['required', 'integer', Rule::in($hotelIds->all())],
            'reservation_id' => ['nullable', 'integer', Rule::exists('reservations', 'id')->where(fn($q) => $q->whereIn('hotel_id', $hotelIds))],
            'type' => ['required', Rule::in(['income', 'expense'])],
            'category' => ['required', 'string', 'max:80'],
            'description' => ['required', 'string', 'max:180'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:9999999999'],
            'status' => ['required', Rule::in(['pending', 'paid'])],
            'due_date' => ['nullable', 'date_format:Y-m-d'],
            'payment_method' => ['nullable', 'string', 'max:60'],
            'reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        if (!empty($data['reservation_id']) && !Reservation::whereKey($data['reservation_id'])->where('hotel_id', $data['hotel_id'])->exists()) {
            return back()->withInput()->withErrors(['reservation_id' => 'Rezervasyon seçilen işletmeye ait değil.']);
        }
        $data['created_by'] = $request->user()->id;
        $data['paid_at'] = $data['status'] === 'paid' ? now() : null;
        HotelFinancialEntry::create($data);
        return back()->with('ok', 'Cari kayıt eklendi.');
    }

    public function update(Request $request, HotelFinancialEntry $entry)
    {
        $this->own($request, $entry);
        $data = $request->validate([
            'category' => ['required', 'string', 'max:80'],
            'description' => ['required', 'string', 'max:180'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:9999999999'],
            'due_date' => ['nullable', 'date_format:Y-m-d'],
            'payment_method' => ['nullable', 'string', 'max:60'],
            'reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $error = DB::transaction(function () use ($entry, $data) {
            $locked = HotelFinancialEntry::query()->lockForUpdate()->findOrFail($entry->id);
            if ($locked->status !== 'pending') return 'Yalnızca bekleyen kayıt düzenlenebilir; tahsil edilmiş kayıtlar denetim izi için kilitlidir.';
            if (\App\Models\ReservationExtension::where('hotel_financial_entry_id', $locked->id)->exists()) return 'Onaylanmış uzatma ücretinin tutarı ve kaydı değiştirilemez.';
            if ($locked->addonOrder()->exists()) return 'Onaylanmış ek hizmet cari kaydının tutarı değiştirilemez; müşteri talebi ve tahsilat kaydı aynı tutarda kalmalıdır.';
            $locked->update($data);
            return null;
        });
        if ($error) return back()->withErrors(['entry' => $error]);
        return back()->with('ok', 'Cari kayıt güncellendi.');
    }

    public function settle(Request $request, HotelFinancialEntry $entry)
    {
        $this->own($request, $entry);
        $data = $request->validate(['action' => ['required', Rule::in(['pay', 'cancel'])], 'payment_method' => ['nullable', 'string', 'max:60']]);
        $result = DB::transaction(function () use ($entry, $data, $request) {
            $locked = HotelFinancialEntry::query()->lockForUpdate()->findOrFail($entry->id);
            if ($locked->status !== 'pending') return 'Bu kayıt bekleyen durumda değil.';
            $addon = $locked->addonOrder()->lockForUpdate()->first();
            if ($addon && $addon->status !== 'approved') return 'Bağlı ek hizmet talebi artık tahsil edilebilir durumda değil.';
            $extension = \App\Models\ReservationExtension::query()->where('hotel_financial_entry_id', $locked->id)->lockForUpdate()->first();
            if ($extension && $data['action'] === 'cancel') return 'Onaylanmış uzatma ücreti rezervasyona bağlıdır; tahsilat kaydı iptal edilemez.';
            if ($data['action'] === 'cancel') {
                $locked->update(['status' => 'cancelled']);
                if ($addon) $addon->update(['status' => 'cancelled', 'payment_status' => 'cancelled']);
            } else {
                $locked->update(['status' => 'paid', 'paid_at' => now(), 'payment_method' => $data['payment_method'] ?? $locked->payment_method]);
                if ($addon) $addon->update(['payment_status' => 'paid']);
            }
            return null;
        });
        if ($result) return back()->withErrors(['entry' => $result]);
        $entry->refresh();
        $addon = $entry->addonOrder()->with('reservation')->first();
        if ($addon) {
            $text = $data['action'] === 'pay'
                ? 'Ek hizmet için ' . number_format((float)$entry->amount, 2, ',', '.') . ' ₺ tahsilat işletme tarafından kaydedildi.'
                : 'Ek hizmet talebi işletme tarafından iptal edildi; ödeme alınmadı.';
            $message = $addon->reservation->messages()->create(['user_id' => $request->user()->id, 'body' => 'EK HİZMET #' . $addon->id . ': ' . $text]);
            Bildirim::yeniMesaj($addon->reservation, $message, 'otel');
        }
        $extension = \App\Models\ReservationExtension::with('reservation')->where('hotel_financial_entry_id', $entry->id)->first();
        if ($extension && $data['action'] === 'pay') {
            $message = $extension->reservation->messages()->create([
                'user_id' => $request->user()->id,
                'body' => 'REZERVASYON UZATMA #' . $extension->id . ': ' . number_format((float) $entry->amount, 2, ',', '.') . ' ₺ ek ücret işletme tarafından tahsil edildi.',
            ]);
            Bildirim::yeniMesaj($extension->reservation, $message, 'otel');
        }
        return back()->with('ok', $data['action'] === 'pay' ? 'Tahsilat/ödeme kaydedildi.' : 'Kayıt iptal edildi; geçmişten silinmedi.');
    }

    private function own(Request $request, HotelFinancialEntry $entry): void
    {
        abort_unless($request->user()->hotels()->whereKey($entry->hotel_id)->exists(), 404);
    }
}
