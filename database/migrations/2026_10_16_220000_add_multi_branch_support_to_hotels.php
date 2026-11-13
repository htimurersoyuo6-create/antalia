<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('hotels', 'parent_hotel_id')) {
            Schema::table('hotels', function (Blueprint $table) {
                $table->foreignId('parent_hotel_id')->nullable()->after('owner_id')
                    ->constrained('hotels')->nullOnDelete();
                $table->index(['owner_id', 'parent_hotel_id']);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('hotels', 'parent_hotel_id')) {
            Schema::table('hotels', function (Blueprint $table) {
                $table->dropForeign(['parent_hotel_id']);
                $table->dropIndex(['owner_id', 'parent_hotel_id']);
                $table->dropColumn('parent_hotel_id');
            });
        }
    }
};
