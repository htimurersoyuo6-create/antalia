<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\City;
use App\Models\Hotel;
use App\Models\HotelSubscription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class HotelEnrollmentController extends Controller
{
    private function entitlement(Request $request): ?HotelSubscription
    {
        $sub = HotelSubscription::current()->where('requested_by', $request->user()->id)->with('plan')->latest('ends_at')->first();
        if ($sub) return $sub;
        $ids = $request->user()->hotels()->pluck('id');
        return $ids->isEmpty() ? null : HotelSubscription::current()->whereIn('hotel_id', $ids)->with('plan')->latest('ends_at')->first();
    }

    public function create(Request $request)
    {
        $subscription = $this->entitlement($request);
        if (!$subscription) return redirect()->route('panel.subscriptions')->with('subscription_required', 'Tesis ekleyebilmek için önce ücretli aboneliğinizi satın alıp etkinleştirin. Bu sırada işletme panelini demo olarak kullanabilirsiniz.');
        $hotelCount = $request->user()->hotels()->count();
        if ($hotelCount >= (int) ($subscription->plan?->hotel_limit ?? 1)) {
            return redirect()->route('panel.subscriptions')->with('subscription_limit', 'Mevcut planınızdaki tesis ekleme sınırına ulaştınız. Yeni tesis eklemek için planınızı yükseltin.');
        }
        $cities = City::with('districts')->orderBy('name')->get();
        return view('panel.hotel-create', compact('cities', 'subscription'));
    }

    public function store(Request $request)
    {
        $subscription = $this->entitlement($request);
        if (!$subscription) return redirect()->route('panel.subscriptions')->with('subscription_required', 'Tesis ekleyebilmek için önce ücretli aboneliğinizi satın alıp etkinleştirin.');
        $data = $request->validate([
            'name' => 'required|string|max:160', 'city_id' => 'required|exists:cities,id',
            'district_id' => 'required|exists:districts,id',
            'phone' => 'required|string|max:30', 'license_no' => 'nullable|string|max:80',
            'accepts_dogs' => 'nullable|boolean', 'accepts_cats' => 'nullable|boolean',
        ]);

        abort_unless(\App\Models\District::whereKey($data['district_id'])->where('city_id', $data['city_id'])->exists(), 422, 'Seçilen ilçe seçilen ile ait değil.');

        $hotel = DB::transaction(function () use ($request, $subscription, $data) {
            $locked = HotelSubscription::whereKey($subscription->id)->lockForUpdate()->with('plan')->firstOrFail();
            // Trial rows are intentionally unpaid. The entitlement is governed
            // by their active status and exact 30-day dates, not a card status.
            $paymentIsValid = $locked->payment_status === 'paid' || $locked->payment_method === 'trial';
            abort_unless($locked->status === 'active' && $paymentIsValid && $locked->starts_at?->lte(now()) && $locked->ends_at?->gt(now()), 403, 'Etkin abonelik veya 30 günlük deneme hakkı bulunamadı.');
            if ($request->user()->hotels()->lockForUpdate()->count() >= (int) ($locked->plan?->hotel_limit ?? 1)) return null;
            $city = City::findOrFail($data['city_id']);
            $base = Str::slug($data['name']) ?: 'pet-otel'; $slug = $base; $n = 2;
            while (Hotel::where('slug', $slug)->exists()) $slug = $base.'-'.$n++;
            $hotel = Hotel::create([
                'owner_id' => $request->user()->id, 'name' => $data['name'], 'slug' => $slug,
                'city_id' => $city->id, 'district_id' => $data['district_id'], 'phone' => $data['phone'],
                'license_no' => $data['license_no'] ?? null, 'accepts_dogs' => $request->boolean('accepts_dogs'),
                'accepts_cats' => $request->boolean('accepts_cats'), 'status' => 'pending',
            ]);
            if (!$locked->hotel_id) $locked->update(['hotel_id' => $hotel->id]);
            return $hotel;
        });
        if (!$hotel) return redirect()->route('panel.subscriptions')->with('subscription_limit', 'Tesis ekleme sınırınıza ulaştınız. Planınızı yükselterek daha fazla tesis ekleyin.');

        return redirect()->route('panel.hotel.edit', $hotel)->with('ok', 'Tesis taslağınız oluşturuldu. Fotoğraf, oda ve konaklama bilgilerini tamamlayabilirsiniz; yayın öncesi tesis ekibimiz bilgileri inceler.');
    }
}
