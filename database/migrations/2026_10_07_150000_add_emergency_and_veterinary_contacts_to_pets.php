<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('pets', function (Blueprint $table) {
            $table->string('emergency_contact_name', 120)->nullable();
            $table->string('emergency_contact_relationship', 80)->nullable();
            $table->string('emergency_contact_phone', 40)->nullable();
            $table->string('vet_name', 120)->nullable();
            $table->string('vet_clinic', 160)->nullable();
            $table->string('vet_phone', 40)->nullable();
            $table->string('vet_address', 255)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('pets', function (Blueprint $table) {
            $table->dropColumn(['emergency_contact_name', 'emergency_contact_relationship', 'emergency_contact_phone', 'vet_name', 'vet_clinic', 'vet_phone', 'vet_address']);
        });
    }
};
