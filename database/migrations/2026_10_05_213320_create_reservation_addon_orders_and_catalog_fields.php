<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('hotel_extras', 'category')) Schema::table('hotel_extras', fn (Blueprint $table) => $table->string('category', 24)->default('service'));
        if (!Schema::hasColumn('hotel_extras', 'description')) Schema::table('hotel_extras', fn (Blueprint $table) => $table->text('description')->nullable());
        if (!Schema::hasColumn('hotel_extras', 'available_after_booking')) Schema::table('hotel_extras', fn (Blueprint $table) => $table->boolean('available_after_booking')->default(true));

        Schema::create('reservation_addon_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('users')->cascadeOnDelete();
            $table->uuid('request_token')->unique();
            $table->string('status', 20)->default('pending')->index();
            $table->string('payment_status', 20)->default('unpaid');
            $table->decimal('amount', 12, 2)->default(0);
            $table->text('customer_note')->nullable();
            $table->text('hotel_note')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->foreignId('financial_entry_id')->nullable()->unique()->constrained('hotel_financial_entries')->nullOnDelete();
            $table->timestamps();
            $table->index(['reservation_id', 'status']);
            $table->index(['hotel_id', 'status']);
        });

        Schema::create('reservation_addon_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('reservation_addon_orders')->cascadeOnDelete();
            $table->foreignId('hotel_extra_id')->nullable()->constrained('hotel_extras')->nullOnDelete();
            $table->string('name_snapshot', 120);
            $table->string('category_snapshot', 24);
            $table->string('unit_snapshot', 10);
            $table->decimal('unit_price', 10, 2);
            $table->unsignedSmallInteger('quantity');
            $table->decimal('line_total', 12, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservation_addon_items');
        Schema::dropIfExists('reservation_addon_orders');
        foreach (['available_after_booking', 'description', 'category'] as $column) {
            if (Schema::hasColumn('hotel_extras', $column)) Schema::table('hotel_extras', fn (Blueprint $table) => $table->dropColumn($column));
        }
    }
};
