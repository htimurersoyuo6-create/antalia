<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::table('hotels', function (Blueprint $table) {
            if (!Schema::hasColumn('hotels','shop_enabled')) $table->boolean('shop_enabled')->default(false);
            if (!Schema::hasColumn('hotels','shop_fulfillment')) $table->string('shop_fulfillment',16)->default('pickup');
            if (!Schema::hasColumn('hotels','shop_delivery_fee')) $table->decimal('shop_delivery_fee',10,2)->default(0);
            if (!Schema::hasColumn('hotels','shop_free_delivery_min')) $table->decimal('shop_free_delivery_min',10,2)->nullable();
        });
        Schema::create('shop_products', function (Blueprint $table) {
            $table->id(); $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $table->string('name',160); $table->string('slug',190)->unique(); $table->string('category',80)->default('Genel');
            $table->string('sku',80)->nullable(); $table->text('description')->nullable();
            $table->decimal('price',12,2); $table->unsignedInteger('stock')->default(0);
            $table->string('image_path',255)->nullable(); $table->boolean('active')->default(false)->index();
            $table->timestamps(); $table->unique(['hotel_id','sku']); $table->index(['hotel_id','active']);
        });
        Schema::create('shop_orders', function (Blueprint $table) {
            $table->id(); $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('hotel_id')->constrained()->restrictOnDelete();
            $table->uuid('checkout_batch'); $table->string('code',20)->unique();
            $table->string('status',20)->default('pending')->index(); $table->string('payment_status',20)->default('unpaid');
            $table->string('payment_method',24); $table->string('fulfillment',16);
            $table->string('buyer_name',120); $table->string('buyer_phone',30);
            $table->string('city',100)->nullable(); $table->string('district',100)->nullable(); $table->string('delivery_address',500)->nullable();
            $table->decimal('items_subtotal',12,2); $table->decimal('shipping_fee',10,2)->default(0); $table->decimal('total',12,2);
            $table->decimal('commission_rate',5,2)->default(0); $table->decimal('commission_amount',12,2)->default(0);
            $table->text('customer_note')->nullable(); $table->text('seller_note')->nullable();
            $table->string('carrier',80)->nullable(); $table->string('tracking_code',120)->nullable(); $table->string('tracking_url',500)->nullable();
            $table->timestamp('paid_at')->nullable(); $table->timestamp('shipped_at')->nullable(); $table->timestamp('delivered_at')->nullable(); $table->timestamp('stock_restored_at')->nullable();
            $table->timestamps(); $table->unique(['user_id','checkout_batch','hotel_id']); $table->index(['hotel_id','status']); $table->index(['user_id','created_at']);
        });
        Schema::create('shop_order_items', function (Blueprint $table) {
            $table->id(); $table->foreignId('shop_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shop_product_id')->nullable()->constrained('shop_products')->nullOnDelete();
            $table->string('name_snapshot',160); $table->string('sku_snapshot',80)->nullable(); $table->string('image_snapshot',255)->nullable();
            $table->decimal('unit_price',12,2); $table->unsignedSmallInteger('quantity'); $table->decimal('line_total',12,2); $table->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('shop_order_items'); Schema::dropIfExists('shop_orders'); Schema::dropIfExists('shop_products');
        Schema::table('hotels', function (Blueprint $table) { foreach(['shop_enabled','shop_fulfillment','shop_delivery_fee','shop_free_delivery_min'] as $c) if(Schema::hasColumn('hotels',$c)) $table->dropColumn($c); });
    }
};
