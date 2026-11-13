<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Reservation;
use App\Models\ShopOrder;
use App\Services\InStayShopService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class InStayShopController extends Controller
{
    public function store(Request $request, Reservation $reservation, InStayShopService $shop): RedirectResponse
    {
        $this->authorizeReservation($request, $reservation);

        $data = $request->validate([
            'product_id' => ['required', 'integer', 'exists:shop_products,id'],
            'quantity' => ['required', 'integer', 'min:1', 'max:20'],
        ]);

        $order = $shop->addItem($reservation, (int) $data['product_id'], (int) $data['quantity']);

        return back()->with('ok', 'Ürün konaklama hesabına eklendi. Toplam: '.number_format((float) $order->total, 2, ',', '.').' TL');
    }

    public function complete(Request $request, Reservation $reservation, InStayShopService $shop): RedirectResponse
    {
        $this->authorizeReservation($request, $reservation);

        $order = ShopOrder::query()
            ->where('reservation_id', $reservation->id)
            ->where('order_channel', 'in_stay')
            ->where('status', '!=', 'cancelled')
            ->latest()
            ->first();

        abort_unless($order, 404);

        $shop->complete($order);

        return back()->with('ok', 'Konaklama içi sipariş teslim edildi ve hesaba işlendi.');
    }

    private function authorizeReservation(Request $request, Reservation $reservation): void
    {
        $user = $request->user();

        abort_unless($user->isAdmin() || (int) $reservation->hotel->owner_id === (int) $user->id, 403);
    }
}
