<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('hotels', 'room_info_confirmed_at')) {
            Schema::table('hotels', function (Blueprint $table): void {
                $table->timestamp('room_info_confirmed_at')->nullable()->after('policies');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('hotels', 'room_info_confirmed_at')) {
            Schema::table('hotels', function (Blueprint $table): void {
                $table->dropColumn('room_info_confirmed_at');
            });
        }
    }
};
