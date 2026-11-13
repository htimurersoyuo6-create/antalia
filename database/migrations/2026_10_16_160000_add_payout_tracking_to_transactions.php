<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::table('transactions', function (Blueprint $table) {
            $table->enum('payout_status', ['pending', 'processing', 'paid'])->default('pending')->index();
            $table->timestamp('payout_at')->nullable();
            $table->string('payout_reference', 120)->nullable();
        });
    }
    public function down(): void {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex(['payout_status']);
            $table->dropColumn(['payout_status', 'payout_at', 'payout_reference']);
        });
    }
};
