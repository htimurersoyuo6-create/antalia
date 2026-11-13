<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('reservation_extensions', 'hotel_financial_entry_id')) {
            Schema::table('reservation_extensions', function (Blueprint $table) {
                $table->foreignId('hotel_financial_entry_id')->nullable()->unique()->after('status')
                    ->constrained('hotel_financial_entries')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('reservation_extensions', 'hotel_financial_entry_id')) {
            Schema::table('reservation_extensions', function (Blueprint $table) {
                $table->dropConstrainedForeignId('hotel_financial_entry_id');
            });
        }
    }
};
