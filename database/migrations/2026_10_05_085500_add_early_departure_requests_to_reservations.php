<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->string('early_departure_status', 20)->default('none')->after('status');
            $table->date('early_departure_date')->nullable()->after('early_departure_status');
            $table->text('early_departure_note')->nullable()->after('early_departure_date');
            $table->timestamp('early_departure_requested_at')->nullable()->after('early_departure_note');
            $table->timestamp('early_departure_decided_at')->nullable()->after('early_departure_requested_at');
            $table->decimal('early_departure_refund_amount', 10, 2)->default(0)->after('early_departure_decided_at');
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropColumn(['early_departure_status', 'early_departure_date', 'early_departure_note', 'early_departure_requested_at', 'early_departure_decided_at', 'early_departure_refund_amount']);
        });
    }
};
