<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Canlı veritabanında bulunan ama hiçbir migration'ın oluşturmadığı üç tablo:
 * settings, veterinaries, notifications. Yapılar canlı DB dökümünden (2026-11-01) alındı.
 *
 * Her tablo hasTable ile korunur: canlıda bu migration hiçbir şey değiştirmez,
 * sadece sıfırdan kurulumda eksik tabloları oluşturur. Dosya adı bilerek eski
 * tarihli; 2026_10_13 tarihli veterinaries migration'larından ÖNCE çalışsın diye.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('settings')) {
            Schema::create('settings', function (Blueprint $table) {
                $table->string('skey', 64)->primary();
                $table->text('svalue')->nullable();
            });
        }

        if (!Schema::hasTable('veterinaries')) {
            Schema::create('veterinaries', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('city_id')->nullable()->index();
                $table->unsignedBigInteger('district_id')->nullable()->index();
                $table->string('name', 200);
                $table->string('slug', 220)->unique();
                $table->string('address', 400)->nullable();
                $table->string('phone', 40)->nullable();
                $table->string('whatsapp', 40)->nullable();
                $table->string('email', 190)->nullable();
                $table->string('website', 300)->nullable();
                $table->text('maps_url');
                $table->string('google_place_id', 500)->nullable();
                $table->decimal('lat', 10, 7)->nullable();
                $table->decimal('lng', 10, 7)->nullable();
                $table->decimal('rating', 2, 1)->nullable();
                $table->integer('review_count')->nullable();
                $table->json('opening_hours')->nullable();
                $table->json('services')->nullable();
                $table->text('description')->nullable();
                $table->string('image', 300)->nullable();
                $table->boolean('is_24h')->default(false);
                $table->boolean('is_published')->default(true)->index();
                $table->boolean('featured')->default(false);
                $table->integer('sort')->default(0);
                $table->string('source', 80)->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('notifications')) {
            Schema::create('notifications', function (Blueprint $table) {
                $table->unsignedInteger('id', true);
                $table->unsignedInteger('user_id');
                $table->string('type', 20)->default('system');
                $table->string('title', 190);
                $table->text('body')->nullable();
                $table->string('link', 190)->nullable()->default('');
                $table->boolean('is_read')->default(false);
                $table->dateTime('created_at')->useCurrent();
                $table->index(['user_id', 'is_read', 'id'], 'idx_notif_user');
            });
        }
    }

    public function down(): void
    {
        // Bilerek boş: bu tablolar canlıda eski sistemden geliyor, geri alma ile silinmesin.
    }
};
