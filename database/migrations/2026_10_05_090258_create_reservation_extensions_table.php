<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('reservation_extensions')) {
            return;
        }

        Schema::create('reservation_extensions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
            $table->date('current_check_out');
            $table->date('requested_check_out');
            $table->unsignedSmallInteger('added_nights');
            $table->text('customer_note')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending')->index();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->decimal('quoted_room_total', 10, 2)->default(0);
            $table->decimal('quoted_discount_total', 10, 2)->default(0);
            $table->decimal('quoted_extras_total', 10, 2)->default(0);
            $table->decimal('quoted_total', 10, 2)->default(0);
            $table->text('hotel_note')->nullable();
            $table->timestamps();
            $table->index(['reservation_id', 'status']);
        });
    }

    public function down(): void
    {
        // Keep customer and hotel extension requests if application code is rolled back.
    }
};
