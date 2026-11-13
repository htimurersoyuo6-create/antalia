<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_cloud_settings', function (Blueprint $table) {
            $table->id();
            $table->string('phone_number_id', 80)->nullable();
            $table->text('access_token')->nullable();
            $table->string('api_version', 16)->default('v23.0');
            $table->string('invoice_template', 100)->nullable();
            $table->string('template_language', 16)->default('en_US');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_cloud_settings');
    }
};
