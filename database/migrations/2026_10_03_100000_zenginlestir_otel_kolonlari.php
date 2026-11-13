<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Otel sayfasinin "Kurallar" ve "Hizmetler" bolumleri icin gereken
 * is verisi kolonlari. Kaynak: eski sitenin hotels/units tablolari.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('hotels', 'amenities')) {
            Schema::table('hotels', function (Blueprint $t) {
                $t->text('amenities')->nullable()->after('policies');
                $t->time('check_in')->nullable()->after('amenities');
                $t->time('check_out')->nullable()->after('check_in');
                $t->unsignedSmallInteger('min_stay')->nullable()->after('check_out');
                $t->tinyInteger('featured')->default(0)->after('min_stay');
                $t->string('google_maps_url', 500)->nullable()->after('featured');
                $t->string('video_url', 255)->nullable()->after('google_maps_url');
                $t->string('whatsapp', 30)->nullable()->after('video_url');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('hotels', 'amenities')) {
            Schema::table('hotels', function (Blueprint $t) {
                $t->dropColumn([
                    'amenities', 'check_in', 'check_out', 'min_stay',
                    'featured', 'google_maps_url', 'video_url', 'whatsapp',
                ]);
            });
        }
    }
};