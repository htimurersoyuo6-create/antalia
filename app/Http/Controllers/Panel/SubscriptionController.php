<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Hotel;
use App\Models\HotelSubscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Support\PaytrSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SubscriptionController extends Controller
{
    public function index(Request $request)
    {
        $hotels = $request->user()->hotels()->with(['subscriptions' => fn ($q) => $q->with(['plan', 'payments' => fn ($p) => $p->latest()])->latest()])->orderBy('name')->get();
        $accountSubscriptions = HotelSubscription::whereNull('hotel_id')->where('requested_by', $request->user()->id)->with(['plan', 'payments' => fn ($p) => $p->latest()])->latest()->get();
        $activeSubscription = HotelSubscription::current()->where('requested_by', $request->user()->id)->with('plan')->latest('ends_at')->first();
        if (! $activeSubscription && $hotels->isNotEmpty()) {
            $activeSubscription = HotelSubscription::current()->whereIn('hotel_id', $hotels->pluck('id'))->with('plan')->latest('ends_at')->first();
        }
        $canAddHotel = $activeSubscription && $hotels->count() < (int) ($activeSubscription->plan?->hotel_limit ?? 1);
        $plans = SubscriptionPlan::where('active', true)->orderBy('sort_order')->orderBy('monthly_price')->get();
        $trialAvailable = ! $this->ownerHasPriorSubscription((int) $request->user()->id);
        $cardPaymentsEnabled = PaytrSettings::ready();

        return view('panel.subscriptions', compact('hotels', 'plans', 'cardPaymentsEnabled', 'accountSubscriptions', 'activeSubscription', 'canAddHotel', 'trialAvailable'));
    }

    public function startTrial(Request $request)
    {
        $data = $request->validate(['plan_id' => 'required|exists:subscription_plans,id']);
        $plan = SubscriptionPlan::whereKey($data['plan_id'])->where('active', true)->firstOrFail();
        $started = DB::transaction(function () use ($request, $plan) {
            $user = User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            if ($this->ownerHasPriorSubscription((int) $user->id)) {
                return false;
            }
            HotelSubscription::create([
                'hotel_id' => null, 'plan_id' => $plan->id, 'requested_by' => $user->id,
                'billing_cycle' => 'monthly', 'amount' => 0, 'requested_payment_method' => 'trial',
                'payment_method' => 'trial', 'payment_status' => 'unpaid', 'status' => 'active',
                'starts_at' => now(), 'ends_at' => now()->addDays(30),
            ]);

            return true;
        });
        if (! $started) {
            return back()->withErrors(['trial' => 'Ücretsiz deneme daha önce kullanılmış veya hesabınızda etkin bir abonelik var.']);
        }

        return redirect()->route('panel.subscriptions')->with('ok', '30 günlük ücretsiz denemeniz başladı. Tesis ekleyebilir ve işletme panelinin canlı özelliklerini kullanabilirsiniz. Deneme sonunda devam etmek için ücretli plan seçmeniz gerekir.');
    }

    private function ownerHasPriorSubscription(int $userId): bool
    {
        $ownedHotelIds = Hotel::query()->where('owner_id', $userId)->select('id');

        return HotelSubscription::query()
            ->where(function ($query) use ($userId, $ownedHotelIds) {
                $query->where('requested_by', $userId)
                    ->orWhereIn('hotel_id', $ownedHotelIds);
            })
            ->exists();
    }

    public function purchase(Request $request)
    {
        $data = $request->validate([
            'plan_id' => 'required|exists:subscription_plans,id',
            'billing_cycle' => 'required|in:monthly,yearly',
            'requested_payment_method' => 'required|in:bank_transfer,card',
        ]);
        $plan = SubscriptionPlan::whereKey($data['plan_id'])->where('active', true)->firstOrFail();
        $amount = $data['billing_cycle'] === 'yearly' ? $plan->yearly_price : $plan->monthly_price;
        abort_if(! $amount || (float) $amount <= 0, 422, 'Lütfen ücretli abonelik planı seçin.');
        if ($data['requested_payment_method'] === 'card' && ! PaytrSettings::ready()) {
            return back()->withErrors(['payment' => 'Online kart ödemesi şu anda yapılandırılmıyor. Havale/EFT seçeneğini kullanabilirsiniz.']);
        }

        $subscription = DB::transaction(function () use ($request, $data, $plan, $amount) {
            $existing = HotelSubscription::whereNull('hotel_id')->where('requested_by', $request->user()->id)
                ->whereIn('status', ['awaiting_payment', 'pending_review'])->lockForUpdate()->first();
            if ($existing) {
                return null;
            }

            return HotelSubscription::create([
                'hotel_id' => null, 'plan_id' => $plan->id, 'requested_by' => $request->user()->id,
                'billing_cycle' => $data['billing_cycle'], 'amount' => $amount,
                'requested_payment_method' => $data['requested_payment_method'],
                'status' => $data['requested_payment_method'] === 'bank_transfer' ? 'pending_review' : 'awaiting_payment',
                'payment_status' => 'unpaid',
            ]);
        });
        if (! $subscription) {
            return back()->withErrors(['subscription' => 'Bekleyen abonelik talebiniz var. Önce mevcut talebinizi tamamlayın.']);
        }

        return redirect()->route('panel.subscriptions')->with('ok', $data['requested_payment_method'] === 'card'
            ? 'Abonelik talebiniz hazır. Güvenli kart ödemesini tamamlayın; gerçek tahsilat doğrulanınca canlı işletme özellikleri ve tesis ekleme açılır.'
            : 'Abonelik talebiniz oluşturuldu. Havale/EFT ve dekont bilgisi yönetimce doğrulanınca tesis ekleme ve canlı işletme işlemleri açılır.');
    }

    public function request(Request $request, Hotel $hotel)
    {
        abort_unless($hotel->owner_id === $request->user()->id, 404);
        $data = $request->validate([
            'plan_id' => 'required|exists:subscription_plans,id',
            'billing_cycle' => 'required|in:monthly,yearly',
            'requested_payment_method' => 'required|in:bank_transfer,card',
        ]);
        $plan = SubscriptionPlan::whereKey($data['plan_id'])->where('active', true)->firstOrFail();
        $amount = $data['billing_cycle'] === 'yearly' ? $plan->yearly_price : $plan->monthly_price;
        abort_if(! $amount || (float) $amount <= 0, 422, 'Ücretsiz plan seçilemez. Lütfen ücretli bir plan seçin.');
        if ($data['requested_payment_method'] === 'card' && ! PaytrSettings::ready()) {
            return back()->withErrors(['payment' => 'Online kart ödemesi henüz yapılandırılmadı. Havale seçeneğini kullanabilirsiniz.']);
        }

        $created = DB::transaction(function () use ($hotel, $plan, $data, $amount, $request) {
            Hotel::whereKey($hotel->id)->lockForUpdate()->firstOrFail();
            if (HotelSubscription::where('hotel_id', $hotel->id)->whereIn('status', ['awaiting_payment', 'pending_review'])->exists()) {
                return false;
            }
            HotelSubscription::create([
                'hotel_id' => $hotel->id, 'plan_id' => $plan->id, 'requested_by' => $request->user()->id,
                'billing_cycle' => $data['billing_cycle'], 'amount' => $amount,
                'requested_payment_method' => $data['requested_payment_method'],
                'status' => 'awaiting_payment', 'payment_status' => 'unpaid',
            ]);

            return true;
        });
        if (! $created) {
            return back()->withErrors(['subscription' => 'Bu tesis için zaten sonuçlanmamış bir abonelik talebi var.']);
        }

        return redirect()->route('panel.subscriptions')->with('ok', $data['requested_payment_method'] === 'card'
            ? 'Abonelik talebiniz kaydedildi. Kartla ödemeyi tamamlayınca PayTR bildirimi aboneliği otomatik etkinleştirir.'
            : 'Abonelik talebiniz kaydedildi. Havale sonrası dekont veya işlem referansı yönetimce doğrulanınca aboneliğiniz etkinleşir.');
    }
}
