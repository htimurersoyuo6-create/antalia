<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('payment_gateway_settings')) return;

        Schema::create('payment_gateway_settings', function (Blueprint $table) {
            $table->string('provider', 40)->primary();
            $table->text('merchant_id')->nullable();
            $table->text('merchant_key')->nullable();
            $table->text('merchant_salt')->nullable();
            $table->boolean('test_mode')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_gateway_settings');
    }
};
