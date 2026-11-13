<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('hotel_subscriptions', 'requested_payment_method')) {
            Schema::table('hotel_subscriptions', function (Blueprint $table) {
                $table->string('requested_payment_method', 24)->nullable()->after('billing_cycle');
            });
        }

        if (!Schema::hasTable('hotel_subscription_payments')) {
            Schema::create('hotel_subscription_payments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('hotel_subscription_id')->constrained()->cascadeOnDelete();
                $table->string('merchant_oid', 64)->unique();
                $table->unsignedBigInteger('amount_kurus');
                $table->string('status', 16)->default('initializing')->index();
                $table->string('provider_payment_id', 120)->nullable();
                $table->boolean('test_mode')->default(true);
                $table->string('error_code', 64)->nullable();
                $table->timestamp('paid_at')->nullable();
                $table->timestamps();
                $table->index(['hotel_subscription_id', 'status']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('hotel_subscription_payments');
        if (Schema::hasColumn('hotel_subscriptions', 'requested_payment_method')) {
            Schema::table('hotel_subscriptions', function (Blueprint $table) {
                $table->dropColumn('requested_payment_method');
            });
        }
    }
};
