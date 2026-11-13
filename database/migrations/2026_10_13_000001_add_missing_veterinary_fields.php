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
            if (!Schema::hasColumn('veterinaries', 'google_place_id')) {
                $table->string('google_place_id', 500)->nullable()->after('maps_url');
            }
            if (!Schema::hasColumn('veterinaries', 'source')) {
                $table->string('source', 100)->nullable()->after('google_place_id');
            }
            // maps_url text yap
            $table->text('maps_url')->change();
        });
    }

    public function down(): void
    {
        Schema::table('veterinaries', function (Blueprint $table) {
            $table->dropColumn(['google_place_id', 'source']);
            $table->string('maps_url', 500)->change();
        });
    }
};
