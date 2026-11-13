<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        if (Schema::connection('adoption')->hasTable('adoption_listings') && !Schema::connection('adoption')->hasColumn('adoption_listings', 'photo_path')) {
            Schema::connection('adoption')->table('adoption_listings', function (Blueprint $table) {
                $table->string('photo_path', 255)->nullable()->after('description');
            });
        }
    }
    public function down(): void {
        if (Schema::connection('adoption')->hasTable('adoption_listings') && Schema::connection('adoption')->hasColumn('adoption_listings', 'photo_path')) {
            Schema::connection('adoption')->table('adoption_listings', fn(Blueprint $table) => $table->dropColumn('photo_path'));
        }
    }
};
