<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Otel galeri gorselleri.
 *
 * cover_path tek kapak görseli içindir. photos alanı galeri
 * görsellerini JSON dizisi olarak tutar (schema.org ImageObject
 * üretimi için).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('hotels', 'photos')) {
            Schema::table('hotels', function (Blueprint $t) {
                $t->text('photos')->nullable()->after('cover_path');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('hotels', 'photos')) {
            Schema::table('hotels', function (Blueprint $t) {
                $t->dropColumn('photos');
            });
        }
    }
};