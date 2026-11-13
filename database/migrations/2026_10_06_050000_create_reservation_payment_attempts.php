<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('reservation_payment_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('conversation_id', 64)->unique();
            $table->string('token', 128)->nullable()->unique();
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
        Schema::dropIfExists('reservation_payment_attempts');
    }
};
