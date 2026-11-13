<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('pets', function (Blueprint $table) {
            $table->string('emergency_contact_2_name', 120)->nullable();
            $table->string('emergency_contact_2_relationship', 80)->nullable();
            $table->string('emergency_contact_2_phone', 40)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('pets', function (Blueprint $table) {
            $table->dropColumn(['emergency_contact_2_name', 'emergency_contact_2_relationship', 'emergency_contact_2_phone']);
        });
    }
};
