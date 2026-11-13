<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('veterinaries')) { return; }
        Schema::table('veterinaries', function (Blueprint $table) {
            $table->text('maps_url')->change();
            $table->string('google_place_id', 500)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('veterinaries', function (Blueprint $table) {
            $table->string('maps_url', 500)->change();
            $table->string('google_place_id', 100)->nullable()->change();
        });
    }
};
