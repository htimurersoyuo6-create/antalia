<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void
    {
        Schema::create('waivers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('reservation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pet_id')->constrained()->cascadeOnDelete();
            $table->string('waiver_type', 40);
            $table->string('version', 40);
            $table->text('content_hash');
            $table->text('signature_data');
            $table->decimal('emergency_budget', 10, 2)->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('signed_at');
            $table->timestamps();
            $table->unique(['reservation_id', 'waiver_type', 'version']);
            $table->index(['user_id', 'signed_at']);
        });
    }
    public function down(): void { Schema::dropIfExists('waivers'); }
};