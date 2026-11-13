<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Hotel;
use App\Models\RevenuePricingRule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PricingRuleController extends Controller
{
    public function index(Request $request)
    {
        $hotels = $this->hotelsFor($request->user());
        $hotelId = (int) ($request->query('hotel') ?: $hotels->first()?->id);
        $hotel = $hotels->firstWhere('id', $hotelId);
        abort_if(! $hotel, 404);

        $rules = RevenuePricingRule::query()
            ->where('hotel_id', $hotel->id)
            ->orderByDesc('start_date')
            ->get();

        return view('panel.pricing-rules', compact('hotels', 'hotel', 'rules'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'hotel_id' => 'required|integer',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'min_nights' => 'nullable|integer|min:1|max:30',
            'base_multiplier_percent' => 'required|integer|min:50|max:300',
            'surcharge_percent' => 'nullable|numeric|min:-50|max:200',
            'is_active' => 'nullable|boolean',
        ]);

        $hotel = $this->hotelsFor($request->user())->firstWhere('id', (int) $data['hotel_id']);
        abort_if(! $hotel, 403);

        RevenuePricingRule::create([
            'hotel_id' => $hotel->id,
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'min_nights' => $data['min_nights'] ?? 1,
            'base_multiplier_percent' => $data['base_multiplier_percent'],
            'surcharge_percent' => $data['surcharge_percent'] ?? 0,
            'is_active' => (bool) ($data['is_active'] ?? true),
        ]);

        return redirect()->route('panel.pricing.index', ['hotel' => $hotel->id])->with('ok', 'Fiyat kuralı eklendi.');
    }

    public function destroy(Request $request, RevenuePricingRule $rule): RedirectResponse
    {
        abort_unless($this->hotelsFor($request->user())->contains('id', $rule->hotel_id), 403);
        $hotelId = $rule->hotel_id;
        $rule->delete();

        return redirect()->route('panel.pricing.index', ['hotel' => $hotelId])->with('ok', 'Fiyat kuralı silindi.');
    }

    private function hotelsFor($user)
    {
        return $user->isAdmin() ? Hotel::query()->orderBy('name')->get() : $user->hotels()->orderBy('name')->get();
    }
}
