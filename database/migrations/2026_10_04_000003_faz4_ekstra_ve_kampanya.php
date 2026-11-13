<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Faz 4: otel bazli ekstra hizmetler + gece sayisina gore indirim.
 * (Canli veritabaninda eski sistemden kalma bos bir `extras` tablosu var; ona dokunulmaz, yeni tablo `hotel_extras`.)
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('hotel_extras')) {
            Schema::create('hotel_extras', function (Blueprint $t) {
                $t->id();
                $t->foreignId('hotel_id')->constrained()->cascadeOnDelete();
                $t->string('name', 120);
                $t->decimal('price', 10, 2);
                $t->enum('unit', ['stay', 'night'])->default('stay');   // konaklama basina / gece basina
                $t->boolean('active')->default(true);
                $t->timestamps();
            });
        }
        if (!Schema::hasTable('hotel_discounts')) {
            Schema::create('hotel_discounts', function (Blueprint $t) {
                $t->id();
                $t->foreignId('hotel_id')->constrained()->cascadeOnDelete();
                $t->unsignedSmallInteger('min_nights');
                $t->unsignedTinyInteger('percent');
                $t->boolean('active')->default(true);
                $t->timestamps();
                $t->unique(['hotel_id', 'min_nights']);
            });
        }
        if (!Schema::hasTable('reservation_extras')) {
            Schema::create('reservation_extras', function (Blueprint $t) {
                $t->id();
                $t->foreignId('reservation_id')->constrained()->cascadeOnDelete();
                $t->unsignedBigInteger('hotel_extra_id')->nullable();
                $t->string('name', 120);
                $t->string('unit', 10)->default('stay');
                $t->decimal('price', 10, 2);
                $t->unsignedSmallInteger('quantity')->default(1);
                $t->decimal('total', 10, 2);
            });
        }
        Schema::table('reservations', function (Blueprint $t) {
            if (!Schema::hasColumn('reservations', 'extras_total'))   $t->decimal('extras_total', 10, 2)->default(0)->after('total_price');
            if (!Schema::hasColumn('reservations', 'discount_total')) $t->decimal('discount_total', 10, 2)->default(0)->after('total_price');
        });
    }

    public function down(): void
    {
        // Veri kaybi olmasin diye bilerek bos.
    }
};
