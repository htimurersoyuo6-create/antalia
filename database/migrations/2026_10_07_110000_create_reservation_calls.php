<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('reservation_calls', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('reservation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('caller_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('recipient_id')->constrained('users')->cascadeOnDelete();
            $table->string('media_type', 12);
            $table->string('status', 16)->default('ringing');
            $table->timestamp('caller_consented_at')->nullable();
            $table->timestamp('recipient_consented_at')->nullable();
            $table->timestamp('caller_notice_at')->nullable();
            $table->timestamp('recipient_notice_at')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('answered_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();
            $table->index(['recipient_id', 'status', 'created_at']);
            $table->index(['reservation_id', 'status']);
        });

        Schema::create('reservation_call_signals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('call_id')->constrained('reservation_calls')->cascadeOnDelete();
            $table->foreignId('from_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('to_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('signal_type', 16);
            $table->longText('payload_encrypted')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['call_id', 'id']);
            $table->index(['to_user_id', 'created_at']);
        });

        Schema::create('reservation_call_recordings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('call_id')->unique()->constrained('reservation_calls')->cascadeOnDelete();
            $table->foreignId('recorder_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('status', 16)->default('recording');
            $table->string('mime_type', 80)->default('video/webm');
            $table->unsignedInteger('chunk_count')->default(0);
            $table->unsignedBigInteger('byte_size')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamp('expires_at');
            $table->timestamps();
            $table->index(['status', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservation_call_signals');
        Schema::dropIfExists('reservation_call_recordings');
        Schema::dropIfExists('reservation_calls');
    }
};
