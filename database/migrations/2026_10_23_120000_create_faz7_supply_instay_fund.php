<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasColumn('shop_products', 'audience')) {
            Schema::table('shop_products', function (Blueprint $table): void {
                $table->string('audience', 12)->default('b2c')->after('category');
                $table->string('supplier', 160)->nullable()->after('audience');
                $table->unsignedInteger('min_order_qty')->default(1)->after('stock');
            });
        }

        if (! Schema::hasColumn('shop_orders', 'order_channel')) {
            Schema::table('shop_orders', function (Blueprint $table): void {
                $table->string('order_channel', 12)->default('b2c')->after('code');
                $table->foreignId('reservation_id')->nullable()->after('hotel_id')->constrained()->nullOnDelete();
                $table->foreignId('buyer_hotel_id')->nullable()->after('reservation_id')->constrained('hotels')->nullOnDelete();
            });
        }

        if (! Schema::hasTable('pet_fund_entries')) {
            Schema::create('pet_fund_entries', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('pet_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('reservation_id')->nullable()->constrained()->nullOnDelete();
                $table->string('type', 16)->default('donation');
                $table->integer('amount_cents');
                $table->string('status', 16)->default('confirmed');
                $table->string('note', 255)->nullable();
                $table->timestamps();
                $table->index(['pet_id', 'type', 'status']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('pet_fund_entries');

        Schema::table('shop_orders', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('reservation_id');
            $table->dropConstrainedForeignId('buyer_hotel_id');
            $table->dropColumn('order_channel');
        });

        Schema::table('shop_products', function (Blueprint $table): void {
            $table->dropColumn(['audience', 'supplier', 'min_order_qty']);
        });
    }
};
