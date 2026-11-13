<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('webhook_events')) {
            Schema::create('webhook_events', function (Blueprint $table): void {
                $table->id();
                $table->string('provider', 24);
                $table->string('event_type', 80)->nullable();
                $table->string('external_id', 120)->nullable();
                $table->json('payload')->nullable();
                $table->boolean('signature_valid')->default(false);
                $table->string('status', 16)->default('received');
                $table->unsignedTinyInteger('attempts')->default(0);
                $table->text('last_error')->nullable();
                $table->timestamp('processed_at')->nullable();
                $table->timestamps();
                $table->unique(['provider', 'external_id']);
                $table->index(['status', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_events');
    }
};
