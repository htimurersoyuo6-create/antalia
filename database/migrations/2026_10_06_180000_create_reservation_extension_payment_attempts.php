<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('reservation_extension_payment_attempts')) {
            if (\Illuminate\Support\Facades\DB::table('reservation_extension_payment_attempts')->exists()) {
                throw new \RuntimeException('Refusing to replace a populated reservation extension payment table.');
            }
            Schema::drop('reservation_extension_payment_attempts');
        }

        Schema::create('reservation_extension_payment_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_extension_id')->unique('rextpay_extension_unique');
            $table->foreign('reservation_extension_id', 'rextpay_extension_fk')->references('id')->on('reservation_extensions')->cascadeOnDelete();
            $table->foreignId('user_id');
            $table->foreign('user_id', 'rextpay_user_fk')->references('id')->on('users')->cascadeOnDelete();
            $table->string('conversation_id', 64)->unique('rextpay_conversation_unique');
            $table->string('token', 128)->nullable()->unique('rextpay_token_unique');
            $table->text('checkout_url')->nullable();
            $table->decimal('amount', 12, 2);
            $table->char('currency', 3)->default('TRY');
            $table->string('status', 16)->default('initializing')->index();
            $table->unsignedInteger('attempt_count')->default(0);
            $table->string('provider_payment_id', 100)->nullable();
            $table->string('error_code', 64)->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservation_extension_payment_attempts');
    }
};
