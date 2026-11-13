<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('reservation_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('pending');
            $table->string('invoice_type', 20)->nullable();
            $table->string('invoice_number', 100)->nullable();
            $table->string('provider_uuid', 100)->nullable();
            $table->date('issued_at')->nullable();
            $table->decimal('amount', 10, 2)->default(0);
            $table->string('currency', 3)->default('TRY');
            $table->string('document_path', 500)->nullable();
            $table->string('document_mime', 120)->nullable();
            $table->string('email_status', 24)->default('pending');
            $table->timestamp('email_sent_at')->nullable();
            $table->string('email_error', 500)->nullable();
            $table->string('whatsapp_status', 24)->default('not_configured');
            $table->timestamp('whatsapp_sent_at')->nullable();
            $table->timestamps();
            $table->index(['hotel_id', 'status']);
        });

        // Create pending records for historical, non-sandbox payments as well.
        DB::table('reservations')->whereIn('status', ['confirmed', 'completed'])
            ->where('payment_status', 'paid')->where(function ($query) {
                $query->whereNull('payment_method')->orWhere('payment_method', '!=', 'iyzico_sandbox');
            })->orderBy('id')->chunkById(500, function ($reservations) {
                $now = now();
                DB::table('reservation_invoices')->insertOrIgnore($reservations->map(fn ($reservation) => [
                    'reservation_id' => $reservation->id, 'hotel_id' => $reservation->hotel_id,
                    'status' => 'pending', 'amount' => $reservation->total_price, 'currency' => 'TRY',
                    'email_status' => 'pending', 'whatsapp_status' => 'not_configured',
                    'created_at' => $now, 'updated_at' => $now,
                ])->all());
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservation_invoices');
    }
};
