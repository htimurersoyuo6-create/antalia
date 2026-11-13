<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HotelSubscription;
use App\Models\SubscriptionPlan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SubscriptionController extends Controller
{
    public function index()
    {
        return view('admin.subscriptions', [
            'plans' => SubscriptionPlan::withCount('subscriptions')->orderBy('sort_order')->orderBy('monthly_price')->get(),
            'subscriptions' => HotelSubscription::with(['hotel:id,name,owner_id', 'hotel.owner:id,name,email', 'plan:id,name', 'requester:id,name,email', 'payments'])
                ->latest()->paginate(25),
            'paytrStatus' => \App\Support\PaytrSettings::status(),
        ]);
    }

    public function savePlan(Request $request, ?SubscriptionPlan $plan = null)
    {
        $data = $request->validate([
            'name' => 'required|string|max:100', 'description' => 'nullable|string|max:1000',
            'monthly_price' => 'required|numeric|min:1|max:1000000', 'yearly_price' => 'nullable|numeric|min:1|max:10000000',
            'hotel_limit' => 'required|integer|min:1|max:500', 'features_text' => 'nullable|string|max:3000',
            'active' => 'nullable|boolean', 'sort_order' => 'nullable|integer|min:0|max:1000',
        ]);
        $plan ??= new SubscriptionPlan();
        $plan->fill([
            'name' => $data['name'], 'description' => $data['description'] ?? null,
            'monthly_price' => $data['monthly_price'], 'yearly_price' => $data['yearly_price'] ?? null,
            'hotel_limit' => $data['hotel_limit'],
            'features' => collect(preg_split('/\r\n|\r|\n/', $data['features_text'] ?? ''))->map(fn ($x) => trim($x))->filter()->take(20)->values()->all(),
            'active' => $request->boolean('active'), 'sort_order' => $data['sort_order'] ?? 0,
        ])->save();
        return back()->with('ok', 'Ücretli abonelik planı kaydedildi.');
    }

    public function decide(Request $request, HotelSubscription $subscription)
    {
        $data = $request->validate([
            'action' => 'required|in:mark_paid_and_activate,reject',
            'payment_method' => 'required_if:action,mark_paid_and_activate|nullable|in:bank_transfer,other',
            'payment_reference' => 'required_if:action,mark_paid_and_activate|nullable|string|max:120', 'admin_note' => 'nullable|string|max:1000',
        ]);
        $handled = DB::transaction(function () use ($request, $subscription, $data) {
            $locked = HotelSubscription::whereKey($subscription->id)->lockForUpdate()->firstOrFail();
            // Prevent stale forms, repeated requests, or direct POSTs from
            // changing an already-paid, active, expired, or cancelled record.
            if (!in_array($locked->status, ['awaiting_payment', 'pending_review'], true)) return false;
            if ($data['action'] === 'reject') {
                $locked->update(['status' => 'cancelled', 'admin_note' => $data['admin_note'] ?? null, 'approved_by' => $request->user()->id]);
                return true;
            }
            $start = HotelSubscription::where('status', 'active')->where('payment_status', 'paid')->where('ends_at', '>', now())
                ->when($locked->hotel_id, fn ($q) => $q->where('hotel_id', $locked->hotel_id), fn ($q) => $q->where('requested_by', $locked->requested_by))
                ->max('ends_at');
            $start = $start ? \Illuminate\Support\Carbon::parse($start) : now();
            $end = $start->copy()->add($locked->billing_cycle === 'yearly' ? 'year' : 'month');
            $locked->update([
                'status' => 'active', 'payment_status' => 'paid', 'payment_method' => $data['payment_method'],
                'payment_reference' => $data['payment_reference'] ?? null, 'paid_at' => $start,
                'starts_at' => $start, 'ends_at' => $end, 'approved_by' => $request->user()->id,
                'admin_note' => $data['admin_note'] ?? null,
            ]);
            // If this plan expired and the expiry job took the hotel offline,
            // bring it back only when it was suspended by that job. Preserve
            // pending/manual moderation states as-is.
            if ($locked->hotel_id) \App\Models\Hotel::whereKey($locked->hotel_id)->where(function ($q) {
                $q->where('status', 'pending')->orWhere(function ($s) {
                    $s->where('status', 'suspended')->where('subscription_suspended', true);
                });
            })->update(['status' => 'active', 'subscription_suspended' => false]);
            return true;
        });
        if (!$handled) return back()->withErrors(['subscription' => 'Bu abonelik kaydı daha önce sonuçlandırılmış; sayfayı yenileyin.']);
        return back()->with('ok', $data['action'] === 'reject' ? 'Abonelik talebi reddedildi.' : 'Ödeme kaydedildi ve abonelik etkinleştirildi.');
    }
}
