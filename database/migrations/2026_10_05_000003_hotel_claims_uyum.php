<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Canlidaki `hotel_claims` tablosu (contact_phone, relationship, message, decided_by) ile uyum:
 * kod bu kolonlari kullanir; yalnizca eksik iki istege bagli alan eklenir (yetkili adi, belge no).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('hotel_claims')) return;
        Schema::table('hotel_claims', function (Blueprint $t) {
            if (!Schema::hasColumn('hotel_claims', 'name'))       $t->string('name', 120)->nullable();
            if (!Schema::hasColumn('hotel_claims', 'license_no')) $t->string('license_no', 80)->nullable();
        });
    }

    public function down(): void {}
};
