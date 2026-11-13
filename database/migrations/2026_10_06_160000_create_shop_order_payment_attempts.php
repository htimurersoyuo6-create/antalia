<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void { Schema::create('shop_order_payment_attempts', function(Blueprint $table) {
  $table->uuid('id')->primary(); $table->uuid('checkout_batch')->unique();
  $table->string('conversation_id',64)->unique(); $table->string('token',128)->nullable()->unique();
  $table->text('checkout_url')->nullable(); $table->decimal('amount',12,2); $table->string('currency',3)->default('TRY');
  $table->string('status',24)->default('initializing')->index(); $table->unsignedSmallInteger('attempt_count')->default(1);
  $table->string('provider_payment_id',80)->nullable(); $table->string('error_code',80)->nullable();
  $table->string('error_message',500)->nullable(); $table->timestamp('paid_at')->nullable(); $table->timestamps();
 }); }
 public function down(): void { Schema::dropIfExists('shop_order_payment_attempts'); }
};
