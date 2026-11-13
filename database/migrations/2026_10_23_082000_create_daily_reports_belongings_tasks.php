<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void
    {
        Schema::create('daily_reports', function (Blueprint $table): void {
            $table->id(); $table->foreignId('reservation_id')->constrained()->cascadeOnDelete(); $table->foreignId('hotel_id')->constrained()->cascadeOnDelete(); $table->foreignId('pet_id')->constrained()->cascadeOnDelete(); $table->foreignId('staff_id')->constrained('users')->cascadeOnDelete(); $table->date('report_date'); $table->string('feeding_status', 24)->default('not_recorded'); $table->unsignedInteger('feeding_grams')->nullable(); $table->string('toilet_status', 24)->default('not_recorded'); $table->string('mood_status', 24)->default('not_recorded'); $table->text('notes')->nullable(); $table->json('photos')->nullable(); $table->timestamps(); $table->unique(['reservation_id', 'report_date']); $table->index(['hotel_id', 'report_date']);
        });
        Schema::create('reservation_belongings', function (Blueprint $table): void {
            $table->id(); $table->foreignId('reservation_id')->unique()->constrained()->cascadeOnDelete(); $table->foreignId('hotel_id')->constrained()->cascadeOnDelete(); $table->foreignId('pet_id')->constrained()->cascadeOnDelete(); $table->foreignId('checked_in_by')->nullable()->constrained('users')->nullOnDelete(); $table->foreignId('checked_out_by')->nullable()->constrained('users')->nullOnDelete(); $table->text('food_details')->nullable(); $table->unsignedInteger('food_daily_gram')->nullable(); $table->json('medications')->nullable(); $table->json('accessories')->nullable(); $table->json('check_in_photos')->nullable(); $table->json('check_out_photos')->nullable(); $table->text('check_in_signature')->nullable(); $table->text('check_out_signature')->nullable(); $table->timestamp('checked_in_at')->nullable(); $table->timestamp('checked_out_at')->nullable(); $table->timestamps();
        });
        Schema::create('pet_tasks', function (Blueprint $table): void {
            $table->id(); $table->foreignId('hotel_id')->constrained()->cascadeOnDelete(); $table->foreignId('reservation_id')->constrained()->cascadeOnDelete(); $table->foreignId('pet_id')->constrained()->cascadeOnDelete(); $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete(); $table->string('task_type', 24); $table->string('title', 160); $table->text('notes')->nullable(); $table->timestamp('scheduled_at'); $table->timestamp('completed_at')->nullable(); $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete(); $table->string('status', 20)->default('pending'); $table->timestamps(); $table->index(['hotel_id', 'status', 'scheduled_at']); $table->index(['reservation_id', 'scheduled_at']);
        });
    }
    public function down(): void { Schema::dropIfExists('pet_tasks'); Schema::dropIfExists('reservation_belongings'); Schema::dropIfExists('daily_reports'); }
};