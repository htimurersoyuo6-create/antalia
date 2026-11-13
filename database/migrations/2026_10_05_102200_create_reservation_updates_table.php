<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('reservation_updates')) {
            return;
        }

        Schema::create('reservation_updates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('headline', 120);
            $table->text('body')->nullable();
            $table->string('photo_path')->nullable();
            $table->timestamps();
            $table->index(['reservation_id', 'id']);
        });
    }

    public function down(): void
    {
        // Keep guest updates and photos when a code rollback is needed.
    }
};
