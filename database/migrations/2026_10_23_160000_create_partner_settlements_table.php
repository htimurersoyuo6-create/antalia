<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('partner_settlements')) {
            Schema::create('partner_settlements', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('partner_hotel_id')->constrained('partner_hotels')->cascadeOnDelete();
                $table->char('period', 7);
                $table->unsignedInteger('booking_count')->default(0);
                $table->unsignedBigInteger('gross_cents')->default(0);
                $table->unsignedBigInteger('commission_cents')->default(0);
                $table->unsignedBigInteger('net_cents')->default(0);
                $table->string('status', 16)->default('draft');
                $table->timestamp('issued_at')->nullable();
                $table->timestamp('paid_at')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->unique(['partner_hotel_id', 'period']);
                $table->index(['period', 'status']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_settlements');
    }
};
