<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Reservation;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Models\ShopProduct;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InStayShopService
{
    public function openOrder(Reservation $reservation): ShopOrder
    {
        return ShopOrder::query()->firstOrCreate(
            [
                'reservation_id' => $reservation->id,
                'order_channel' => 'in_stay',
            ],
            [
                'user_id' => $reservation->user_id,
                'hotel_id' => $reservation->hotel_id,
                'code' => 'IS'.strtoupper(bin2hex(random_bytes(4))),
                'status' => 'pending',
                'payment_status' => 'unpaid',
                'payment_method' => 'room_charge',
                'buyer_name' => $reservation->user?->name ?? 'Misafir',
                'buyer_phone' => $reservation->user?->phone,
                'items_subtotal' => 0,
                'shipping_fee' => 0,
                'total' => 0,
                'commission_rate' => 0,
                'commission_amount' => 0,
            ]
        );
    }

    public function addItem(Reservation $reservation, int $productId, int $quantity): ShopOrder
    {
        if ($quantity <= 0) {
            throw ValidationException::withMessages(['quantity' => 'Adet sıfırdan büyük olmalıdır.']);
        }

        return DB::transaction(function () use ($reservation, $productId, $quantity): ShopOrder {
            $product = ShopProduct::query()
                ->where('id', $productId)
                ->where('hotel_id', $reservation->hotel_id)
                ->where('active', true)
                ->whereIn('audience', ['b2c', 'both'])
                ->lockForUpdate()
                ->first();

            if (! $product) {
                throw ValidationException::withMessages(['product_id' => 'Ürün bulunamadı.']);
            }

            if ($product->stock < $quantity) {
                throw ValidationException::withMessages(['quantity' => 'Yeterli stok yok.']);
            }

            $order = $this->openOrder($reservation);

            $item = $order->items()
                ->where('shop_product_id', $product->id)
                ->first();

            if ($item) {
                $item->quantity += $quantity;
                $item->line_total = ((float) $item->unit_price) * $item->quantity;
                $item->save();
            } else {
                ShopOrderItem::create([
                    'shop_order_id' => $order->id,
                    'shop_product_id' => $product->id,
                    'name_snapshot' => $product->name,
                    'sku_snapshot' => $product->sku,
                    'image_snapshot' => $product->image_path,
                    'unit_price' => $product->price,
                    'quantity' => $quantity,
                    'line_total' => ((float) $product->price) * $quantity,
                ]);
            }

            $product->decrement('stock', $quantity);

            $subtotal = (float) $order->items()->sum('line_total');
            $order->forceFill([
                'items_subtotal' => $subtotal,
                'total' => $subtotal + (float) $order->shipping_fee,
            ])->save();

            return $order->refresh();
        });
    }

    public function complete(ShopOrder $order): ShopOrder
    {
        $order->forceFill([
            'status' => 'delivered',
            'payment_status' => 'paid',
            'paid_at' => now(),
            'delivered_at' => now(),
        ])->save();

        return $order;
    }
}
