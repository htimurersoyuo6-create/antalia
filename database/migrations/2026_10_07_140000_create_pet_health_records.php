<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('pet_medical_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title', 120);
            $table->string('document_type', 32)->default('health_card');
            $table->string('disk_path');
            $table->string('original_name', 180);
            $table->string('mime_type', 100);
            $table->unsignedInteger('size_bytes');
            $table->boolean('share_with_active_hotel')->default(false);
            $table->timestamps();
            $table->index(['pet_id', 'document_type']);
        });

        Schema::create('pet_vaccinations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('vaccine_name', 120);
            $table->date('administered_at')->nullable();
            $table->date('next_due_at')->nullable()->index();
            $table->string('clinic_name', 160)->nullable();
            $table->string('vet_name', 120)->nullable();
            $table->string('batch_number', 80)->nullable();
            $table->text('notes')->nullable();
            $table->boolean('reminder_enabled')->default(true);
            $table->timestamps();
            $table->index(['user_id', 'next_due_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pet_vaccinations');
        Schema::dropIfExists('pet_medical_documents');
    }
};
