<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Hotel;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Models\ShopProduct;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class SupplyMarketService
{
    public function products(?Hotel $buyerHotel = null)
    {
        return ShopProduct::query()
            ->where('active', true)
            ->whereIn('audience', ['b2b', 'both'])
            ->when($buyerHotel, fn ($query) => $query->where(function ($inner) use ($buyerHotel): void {
                $inner->whereNull('hotel_id')->orWhere('hotel_id', '!=', $buyerHotel->id);
            }))
            ->orderBy('category')
            ->orderBy('name')
            ->get();
    }

    public function placeOrder(Hotel $buyerHotel, int $userId, array $items, ?string $note = null): ShopOrder
    {
        $normalized = [];

        foreach ($items as $row) {
            $productId = (int) ($row['product_id'] ?? 0);
            $quantity = (int) ($row['quantity'] ?? 0);

            if ($productId <= 0 || $quantity <= 0) {
                continue;
            }

            $normalized[$productId] = ($normalized[$productId] ?? 0) + $quantity;
        }

        if ($normalized === []) {
            throw ValidationException::withMessages(['items' => 'En az bir ürün seçmelisiniz.']);
        }

        return DB::transaction(function () use ($buyerHotel, $userId, $normalized, $note): ShopOrder {
            $products = ShopProduct::query()
                ->whereIn('id', array_keys($normalized))
                ->where('active', true)
                ->whereIn('audience', ['b2b', 'both'])
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $subtotalCents = 0;

            foreach ($normalized as $productId => $quantity) {
                $product = $products->get($productId);

                if (! $product) {
                    throw ValidationException::withMessages(['items' => 'Seçilen ürünlerden biri artık mevcut değil.']);
                }

                if ($quantity < (int) $product->min_order_qty) {
                    throw ValidationException::withMessages([
                        'items' => $product->name.' için minimum sipariş adedi '.$product->min_order_qty.'.',
                    ]);
                }

                if ($product->stock < $quantity) {
                    throw ValidationException::withMessages(['items' => $product->name.' için yeterli stok yok.']);
                }

                $subtotalCents += (int) round(((float) $product->price) * 100) * $quantity;
            }

            $order = ShopOrder::create([
                'user_id' => $userId,
                'hotel_id' => null,
                'buyer_hotel_id' => $buyerHotel->id,
                'code' => 'BS'.strtoupper(bin2hex(random_bytes(4))),
                'order_channel' => 'b2b',
                'status' => 'pending',
                'payment_status' => 'unpaid',
                'payment_method' => 'invoice',
                'buyer_name' => $buyerHotel->name,
                'items_subtotal' => $subtotalCents / 100,
                'shipping_fee' => 0,
                'total' => $subtotalCents / 100,
                'commission_rate' => 0,
                'commission_amount' => 0,
                'customer_note' => $note,
            ]);

            foreach ($normalized as $productId => $quantity) {
                $product = $products->get($productId);

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

                $product->decrement('stock', $quantity);
            }

            Log::info('supply_market.order_created', [
                'order_id' => $order->id,
                'buyer_hotel_id' => $buyerHotel->id,
                'total' => $order->total,
            ]);

            return $order;
        });
    }
}
