<?php
namespace Tests\Feature;

use App\Models\City;
use App\Models\District;
use App\Models\Hotel;
use App\Models\HotelSubscription;
use App\Models\SubscriptionPlan;
use App\Models\ShopOrder;
use App\Models\ShopOrderPaymentAttempt;
use App\Models\ShopProduct;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ShopMarketplaceTest extends TestCase
{
    use RefreshDatabase;

    private function makeActorsAndProduct(array $hotelOverrides = []): array
    {
        $owner = User::create(['name' => 'İşletme Sahibi', 'email' => uniqid().'@owner.test', 'password' => 'password123']);
        $owner->role = 'hotel_owner'; $owner->save();
        $buyer = User::create(['name' => 'Müşteri', 'email' => uniqid().'@buyer.test', 'password' => 'password123']);
        $city = City::firstOrCreate(['slug' => 'antalya'], ['name' => 'Antalya']);
        $district = $city->districts()->firstOrCreate(['slug' => 'kemer'], ['name' => 'Kemer']);
        $hotel = Hotel::create(array_merge([
            'owner_id' => $owner->id, 'name' => 'Mağaza Test Oteli', 'slug' => 'magaza-test-'.uniqid(),
            'city_id' => $city->id, 'district_id' => $district->id, 'status' => 'active',
            'accepts_dogs' => true, 'accepts_cats' => true, 'shop_enabled' => true,
            'shop_fulfillment' => 'both', 'shop_delivery_fee' => 75, 'shop_free_delivery_min' => 1000,
        ], $hotelOverrides));
        $plan = SubscriptionPlan::firstOrCreate(['name' => 'Feature Test Plan'], ['monthly_price' => 500, 'active' => true]);
        HotelSubscription::create([
            'hotel_id' => $hotel->id, 'plan_id' => $plan->id, 'requested_by' => $owner->id,
            'billing_cycle' => 'monthly', 'amount' => 500, 'status' => 'active', 'payment_status' => 'paid',
            'payment_method' => 'bank_transfer', 'paid_at' => now(), 'starts_at' => now()->subMinute(), 'ends_at' => now()->addMonth(),
        ]);
        $product = ShopProduct::create([
            'hotel_id' => $hotel->id, 'name' => 'Hassas Dost Maması', 'slug' => 'hassas-dost-mamasi-'.uniqid(),
            'category' => 'Mama', 'price' => 350, 'stock' => 5, 'active' => true,
        ]);
        return [$owner, $buyer, $hotel, $product];
    }

    public function test_customer_can_order_and_seller_can_complete_and_collect_payment(): void
    {
        [$owner, $buyer, $hotel, $product] = $this->makeActorsAndProduct();

        $this->actingAs($buyer)->post(route('shop.cart.add', $product), ['quantity' => 2])->assertRedirect(route('shop.cart'));
        $this->get(route('shop.checkout'))->assertOk();
        $this->post(route('shop.place'), [
            'buyer_name' => 'Müşteri Test', 'buyer_phone' => '05551234567',
            'payment_method' => 'pay_at_delivery', 'customer_note' => 'Kapıda arayın.',
            'orders' => [$hotel->id => ['fulfillment' => 'pickup']],
        ])->assertRedirect(route('shop.orders'));

        $order = ShopOrder::query()->sole();
        $this->assertSame('pending', $order->status);
        $this->assertSame('unpaid', $order->payment_status);
        $this->assertSame('pickup', $order->fulfillment);
        $this->assertEquals(700, (float) $order->total);
        $this->assertSame(3, $product->fresh()->stock);
        $this->get(route('shop.order', $order))->assertOk()->assertSee($order->code);

        $url = "/panel/magaza/otel/{$hotel->id}/siparisler/{$order->id}";
        $this->actingAs($owner)->patch($url, ['action' => 'confirm'])->assertSessionHasNoErrors();
        $this->assertSame('confirmed', $order->fresh()->status);
        $this->patch($url, ['action' => 'prepare'])->assertSessionHasNoErrors();
        $this->assertSame('preparing', $order->fresh()->status);
        $this->patch($url, ['action' => 'deliver'])->assertSessionHasNoErrors();
        $this->assertSame('delivered', $order->fresh()->status);
        $this->patch($url, ['action' => 'mark_paid'])->assertSessionHasNoErrors();
        $this->assertSame('delivered', $order->fresh()->status);
        $this->assertSame('paid', $order->fresh()->payment_status);
        $this->assertNotNull($order->fresh()->paid_at);
    }

    public function test_customer_cannot_cancel_order_while_card_payment_is_pending(): void
    {
        [$owner, $buyer, $hotel, $product] = $this->makeActorsAndProduct();
        $this->actingAs($buyer)->post(route('shop.cart.add', $product), ['quantity' => 1]);
        $this->post(route('shop.place'), [
            'buyer_name' => 'Müşteri Test', 'buyer_phone' => '05551234567',
            'payment_method' => 'pay_at_delivery',
            'orders' => [$hotel->id => ['fulfillment' => 'pickup']],
        ])->assertRedirect(route('shop.orders'));

        $order = ShopOrder::query()->sole();
        $batch = (string) $order->checkout_batch;
        $order->update(['payment_method' => 'iyzico_sandbox']);
        ShopOrderPaymentAttempt::create([
            'id' => (string) Str::uuid(), 'checkout_batch' => $batch,
            'conversation_id' => 'APO-SHOP-' . Str::upper(Str::random(20)),
            'token' => 'sandbox-test-token', 'checkout_url' => 'https://sandbox-api.iyzipay.com/test',
            'amount' => $order->total, 'currency' => 'TRY', 'status' => 'pending',
            'attempt_count' => 1, 'expires_at' => now()->addMinutes(20),
        ]);

        $this->actingAs($buyer)->post(route('shop.cancel', $order))->assertStatus(422);
        $this->assertSame('pending', $order->fresh()->status);
        $this->assertSame('unpaid', $order->fresh()->payment_status);
        $this->assertSame(4, $product->fresh()->stock);
        $this->assertNull($order->fresh()->stock_restored_at);
    }

    public function test_customer_can_cancel_unpaid_order_when_no_card_checkout_is_active(): void
    {
        [$owner, $buyer, $hotel, $product] = $this->makeActorsAndProduct();
        $this->actingAs($buyer)->post(route('shop.cart.add', $product), ['quantity' => 1]);
        $this->post(route('shop.place'), [
            'buyer_name' => 'Müşteri Test', 'buyer_phone' => '05551234567',
            'payment_method' => 'pay_at_delivery',
            'orders' => [$hotel->id => ['fulfillment' => 'pickup']],
        ])->assertRedirect(route('shop.orders'));

        $order = ShopOrder::query()->sole();
        $this->actingAs($buyer)->post(route('shop.cancel', $order))->assertRedirect();
        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertSame('unpaid', $order->fresh()->payment_status);
        $this->assertSame(5, $product->fresh()->stock);
        $this->assertNotNull($order->fresh()->stock_restored_at);
    }

    public function test_delivery_fee_and_free_delivery_threshold_are_applied(): void
    {
        [$owner, $buyer, $hotel, $product] = $this->makeActorsAndProduct();
        $this->actingAs($buyer)->post(route('shop.cart.add', $product), ['quantity' => 2])->assertRedirect(route('shop.cart'));
        $this->post(route('shop.place'), [
            'buyer_name' => 'Müşteri Test', 'buyer_phone' => '05551234567', 'payment_method' => 'pay_at_delivery',
            'orders' => [$hotel->id => ['fulfillment' => 'delivery', 'city' => 'Antalya', 'district' => 'Kemer', 'address' => 'Test Mahallesi 1']],
        ])->assertRedirect(route('shop.orders'));
        $order = ShopOrder::query()->sole();
        $this->assertEquals(75, (float) $order->shipping_fee);
        $this->assertEquals(775, (float) $order->total);
        $this->assertSame('Test Mahallesi 1', $order->delivery_address);

        // A new order above the free-shipping threshold must not pay delivery again.
        $this->post(route('shop.cart.add', $product), ['quantity' => 3])->assertRedirect(route('shop.cart'));
        $this->post(route('shop.place'), [
            'buyer_name' => 'Müşteri Test', 'buyer_phone' => '05551234567', 'payment_method' => 'pay_at_delivery',
            'orders' => [$hotel->id => ['fulfillment' => 'delivery', 'city' => 'Antalya', 'district' => 'Kemer', 'address' => 'Test Mahallesi 1']],
        ])->assertRedirect(route('shop.orders'));
        $this->assertEquals(0, (float) ShopOrder::latest('id')->first()->shipping_fee);
    }

    public function test_seller_and_buyer_cannot_access_other_accounts_shop_records(): void
    {
        [$owner, $buyer, $hotel, $product] = $this->makeActorsAndProduct();
        $other = User::create(['name' => 'Diğer', 'email' => uniqid().'@other.test', 'password' => 'password123']);
        $other->role = 'hotel_owner'; $other->save();
        $this->actingAs($other)->get("/panel/magaza/otel/{$hotel->id}")->assertForbidden();
        $this->actingAs($buyer)->post(route('shop.cart.add', $product), ['quantity' => 1]);
        $this->post(route('shop.place'), [
            'buyer_name' => 'Müşteri Test', 'buyer_phone' => '05551234567', 'payment_method' => 'pay_at_delivery',
            'orders' => [$hotel->id => ['fulfillment' => 'pickup']],
        ]);
        $order = ShopOrder::query()->sole();
        $this->actingAs($other)->get(route('shop.order', $order))->assertNotFound();
    }
}
