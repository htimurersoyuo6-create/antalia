<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('hotels', 'subscription_suspended')) {
            Schema::table('hotels', function (Blueprint $table) {
                $table->boolean('subscription_suspended')->default(false);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('hotels', 'subscription_suspended')) {
            Schema::table('hotels', function (Blueprint $table) {
                $table->dropColumn('subscription_suspended');
            });
        }
    }
};
