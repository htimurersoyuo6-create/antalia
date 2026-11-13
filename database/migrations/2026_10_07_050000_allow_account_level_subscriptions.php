<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            Schema::table('hotel_subscriptions', function (Blueprint $table) {
                $table->dropForeign(['hotel_id']);
                $table->foreignId('hotel_id')->nullable()->change();
                $table->foreign('hotel_id')->references('id')->on('hotels')->nullOnDelete();
            });

            return;
        }

        Schema::table('hotel_subscriptions', function (Blueprint $table) {
            $table->dropForeign('hotel_subscriptions_hotel_id_foreign');
            $table->foreignId('hotel_id')->nullable()->change();
            $table->foreign('hotel_id')->references('id')->on('hotels')->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            Schema::table('hotel_subscriptions', function (Blueprint $table) {
                $table->dropForeign(['hotel_id']);
                $table->foreignId('hotel_id')->nullable(false)->change();
                $table->foreign('hotel_id')->references('id')->on('hotels')->cascadeOnDelete();
            });

            return;
        }

        Schema::table('hotel_subscriptions', function (Blueprint $table) {
            $table->dropForeign('hotel_subscriptions_hotel_id_foreign');
            $table->foreignId('hotel_id')->nullable(false)->change();
            $table->foreign('hotel_id')->references('id')->on('hotels')->cascadeOnDelete();
        });
    }
};
