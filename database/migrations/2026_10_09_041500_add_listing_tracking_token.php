<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contact_messages', function (Blueprint $table) {
            $table->char('listing_token_hash', 64)->nullable()->unique();
            $table->timestamp('listing_token_expires_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('contact_messages', function (Blueprint $table) {
            $table->dropUnique(['listing_token_hash']);
            $table->dropColumn(['listing_token_hash', 'listing_token_expires_at']);
        });
    }
};
