<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('device_tokens')) {
            Schema::create('device_tokens', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('token', 191)->unique();
                $table->enum('platform', ['ios', 'android', 'web', 'unknown'])->default('unknown');
                $table->string('device_id', 64)->nullable()->index();
                $table->string('device_name', 120)->nullable();
                $table->string('app_version', 32)->nullable();
                $table->string('os_version', 32)->nullable();
                $table->ipAddress('last_ip')->nullable();
                $table->timestamp('last_used_at')->nullable();
                $table->timestamp('revoked_at')->nullable();
                $table->timestamps();
                $table->index(['user_id', 'revoked_at']);
            });
        }

        if (! Schema::hasTable('login_histories')) {
            Schema::create('login_histories', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('guard', 16)->default('web');
                $table->ipAddress('ip')->nullable();
                $table->text('user_agent')->nullable();
                $table->string('country', 2)->nullable();
                $table->string('city', 80)->nullable();
                $table->boolean('successful')->default(true);
                $table->string('reason', 32)->nullable();
                $table->timestamp('created_at')->nullable();
                $table->index(['user_id', 'created_at']);
                $table->index(['ip', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('login_histories');
        Schema::dropIfExists('device_tokens');
    }
};
