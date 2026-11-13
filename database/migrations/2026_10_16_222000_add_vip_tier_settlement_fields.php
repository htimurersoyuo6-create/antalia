<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            if (!Schema::hasColumn('reservations', 'vip_tier')) $table->string('vip_tier', 20)->default('bronze');
            if (!Schema::hasColumn('reservations', 'vip_discount_total')) $table->decimal('vip_discount_total', 10, 2)->default(0);
            if (!Schema::hasColumn('reservations', 'vip_platform_discount_total')) $table->decimal('vip_platform_discount_total', 10, 2)->default(0);
        });
        Schema::table('transactions', function (Blueprint $table) {
            if (!Schema::hasColumn('transactions', 'vip_discount_total')) $table->decimal('vip_discount_total', 12, 2)->default(0);
            if (!Schema::hasColumn('transactions', 'hotel_discount_total')) $table->decimal('hotel_discount_total', 12, 2)->default(0);
            if (!Schema::hasColumn('transactions', 'platform_discount_amount')) $table->decimal('platform_discount_amount', 12, 2)->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            foreach (['vip_discount_total', 'hotel_discount_total', 'platform_discount_amount'] as $column) {
                if (Schema::hasColumn('transactions', $column)) $table->dropColumn($column);
            }
        });
        Schema::table('reservations', function (Blueprint $table) {
            foreach (['vip_tier', 'vip_discount_total', 'vip_platform_discount_total'] as $column) {
                if (Schema::hasColumn('reservations', $column)) $table->dropColumn($column);
            }
        });
    }
};
