<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('pet_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $table->string('pickup_address', 500);
            $table->string('dropoff_address', 500);
            $table->dateTime('pickup_at');
            $table->string('vehicle_type', 32);
            $table->decimal('distance_km', 8, 2)->nullable();
            $table->decimal('fee', 10, 2)->default(0);
            $table->string('status', 24)->default('requested')->index();
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('pet_transfers'); }
};
