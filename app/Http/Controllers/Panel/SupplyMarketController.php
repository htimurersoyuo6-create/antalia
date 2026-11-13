<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Hotel;
use App\Models\ShopOrder;
use App\Services\SupplyMarketService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SupplyMarketController extends Controller
{
    public function index(Request $request, SupplyMarketService $market): View
    {
        $hotel = $this->resolveHotel($request);

        return view('panel.supply-market', [
            'hotel' => $hotel,
            'hotels' => $user->isAdmin() ? Hotel::query()->orderBy('name')->get() : collect([$hotel]),
            'products' => $market->products($hotel),
            'orders' => ShopOrder::query()->where('buyer_hotel_id', $hotel->id)->withCount('items')->latest()->limit(20)->get(),
        ]);
    }

    public function store(Request $request, SupplyMarketService $market): RedirectResponse
    {
        $hotel = $this->resolveHotel($request);

        $data = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:shop_products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:999'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $order = $market->placeOrder($hotel, (int) $request->user()->id, $data['items'], $data['note'] ?? null);

        return redirect()
            ->route('panel.supply.index')
            ->with('ok', 'Sipariş oluşturuldu: '.$order->code);
    }

    private function resolveHotel(Request $request): Hotel
    {
        return $user->hotels()->firstOrFail();
    }
}
