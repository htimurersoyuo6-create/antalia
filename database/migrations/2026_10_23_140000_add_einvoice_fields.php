<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasColumn('reservation_invoices', 'e_invoice_status')) {
            Schema::table('reservation_invoices', function (Blueprint $table): void {
                $table->string('e_invoice_status', 16)->default('none')->after('invoice_number');
                $table->string('e_invoice_error', 255)->nullable()->after('e_invoice_status');
                $table->timestamp('e_invoice_sent_at')->nullable()->after('e_invoice_error');
            });
        }
    }

    public function down(): void
    {
        Schema::table('reservation_invoices', function (Blueprint $table): void {
            $table->dropColumn(['e_invoice_status', 'e_invoice_error', 'e_invoice_sent_at']);
        });
    }
};
