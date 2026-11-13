<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('hotel_chats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('guest_session_hash', 64)->nullable()->index();
            $table->string('guest_name', 120)->nullable();
            $table->string('guest_phone', 30)->nullable();
            $table->string('status', 20)->default('open')->index();
            $table->timestamp('archived_at')->nullable();
            $table->timestamp('last_message_at')->nullable()->index();
            $table->timestamps();
            $table->index(['hotel_id', 'status', 'last_message_at']);
            $table->index(['user_id', 'status', 'last_message_at']);
        });
        Schema::create('hotel_chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_chat_id')->constrained('hotel_chats')->cascadeOnDelete();
            $table->foreignId('sender_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('sender_type', 20);
            $table->text('body');
            $table->timestamps();
            $table->index(['hotel_chat_id', 'id']);
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('hotel_chat_messages');
        Schema::dropIfExists('hotel_chats');
    }
};
