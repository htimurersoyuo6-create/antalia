<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Canli veritabanindaki `reviews` tablosu eski sistemden kalma sema ile
 * (hotel_id, unit_id, booking_id, status enum, title, reply) duruyor;
 * Laravel kodu ise `reservation_id` ile calisiyordu. Sonuc: yorum kaydi
 * "Unknown column reservation_id" hatasi veriyordu.
 *
 * Bu migration mevcut kolonlari bozmadan eksikleri ekler.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('reviews')) {
            Schema::create('reviews', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('reservation_id')->nullable()->unique();
                $t->unsignedBigInteger('user_id');
                $t->unsignedBigInteger('hotel_id')->index();
                $t->unsignedTinyInteger('rating')->default(5);
                $t->string('title', 150)->nullable();
                $t->text('comment')->nullable();
                $t->text('reply')->nullable();
                $t->enum('status', ['pending', 'published', 'hidden'])->default('published');
                $t->timestamp('created_at')->useCurrent();
            });
            return;
        }
        Schema::table('reviews', function (Blueprint $t) {
            if (!Schema::hasColumn('reviews', 'reservation_id')) {
                $t->unsignedBigInteger('reservation_id')->nullable()->unique()->after('id');
            }
            if (!Schema::hasColumn('reviews', 'title'))  $t->string('title', 150)->nullable();
            if (!Schema::hasColumn('reviews', 'reply'))  $t->text('reply')->nullable();
            if (!Schema::hasColumn('reviews', 'status')) $t->string('status', 20)->default('published');
        });
        if (Schema::hasColumn('reviews', 'published')) {
            DB::table('reviews')->where('published', false)->update(['status' => 'hidden']);
        }
    }

    public function down(): void
    {
        // Geriye donuk veri kaybi olmasin diye bilerek bos birakildi.
    }
};
