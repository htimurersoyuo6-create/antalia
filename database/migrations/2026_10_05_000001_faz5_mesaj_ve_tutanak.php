<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('reservation_messages')) {
            Schema::create('reservation_messages', function (Blueprint $t) {
                $t->id();
                $t->foreignId('reservation_id')->constrained()->cascadeOnDelete();
                $t->foreignId('user_id')->constrained()->cascadeOnDelete();
                $t->text('body');
                $t->timestamp('created_at')->useCurrent();
                $t->index(['reservation_id', 'id']);
            });
        }
        if (!Schema::hasTable('reservation_records')) {
            Schema::create('reservation_records', function (Blueprint $t) {
                $t->id();
                $t->foreignId('reservation_id')->constrained()->cascadeOnDelete();
                $t->enum('type', ['checkin', 'checkout']);
                $t->text('note')->nullable();
                $t->foreignId('created_by')->constrained('users')->cascadeOnDelete();
                $t->timestamp('hotel_signed_at')->nullable();
                $t->timestamp('customer_signed_at')->nullable();
                $t->timestamps();
                $t->unique(['reservation_id', 'type']);
            });
        }
    }

    public function down(): void
    {
        // Veri kaybi olmasin diye bilerek bos.
    }
};
