<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('pets', function (Blueprint $table): void {
            $table->string('microchip_number', 15)->nullable()->unique()->after('species');
        });

        Schema::table('pet_vaccinations', function (Blueprint $table): void {
            $table->string('vaccine_type', 24)->default('other')->after('vaccine_name');
            $table->index(['pet_id', 'vaccine_type', 'next_due_at']);
        });
    }

    public function down(): void
    {
        Schema::table('pet_vaccinations', function (Blueprint $table): void {
            $table->dropIndex(['pet_id', 'vaccine_type', 'next_due_at']);
            $table->dropColumn('vaccine_type');
        });

        Schema::table('pets', function (Blueprint $table): void {
            $table->dropUnique(['microchip_number']);
            $table->dropColumn('microchip_number');
        });
    }
};