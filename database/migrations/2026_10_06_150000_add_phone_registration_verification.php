<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'phone_verified_at')) $table->timestamp('phone_verified_at')->nullable()->after('phone_normalized');
        });
        Schema::create('pending_phone_registrations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('phone_normalized', 16)->unique();
            $table->char('identity_hash', 64)->unique();
            $table->text('registration_payload');
            $table->string('code_hash', 255);
            $table->string('delivery_status', 16)->default('sending');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('expires_at')->index();
            $table->timestamp('last_sent_at')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('pending_phone_registrations');
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'phone_verified_at')) $table->dropColumn('phone_verified_at');
        });
    }
};
