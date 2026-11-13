<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('pet_medical_documents', function (Blueprint $table) {
            $table->boolean('encrypted')->default(false)->after('share_with_active_hotel');
        });
    }

    public function down(): void
    {
        Schema::table('pet_medical_documents', function (Blueprint $table) {
            $table->dropColumn('encrypted');
        });
    }
};
