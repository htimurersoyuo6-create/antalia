<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasColumn('hotels', 'extra_pet_fee')) {
            Schema::table('hotels', function (Blueprint $table): void {
                $table->decimal('extra_pet_fee', 10, 2)->default(0)->after('accepts_cats');
                $table->decimal('late_checkout_fee', 10, 2)->default(0)->after('extra_pet_fee');
                $table->unsignedTinyInteger('late_checkout_hours')->default(2)->after('late_checkout_fee');
            });
        }

        if (! Schema::hasTable('reservation_pets')) {
            Schema::create('reservation_pets', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('reservation_id')->constrained()->cascadeOnDelete();
                $table->foreignId('pet_id')->constrained()->cascadeOnDelete();
                $table->decimal('fee', 10, 2)->default(0);
                $table->timestamps();
                $table->unique(['reservation_id', 'pet_id']);
            });
        }

        if (! Schema::hasColumn('reservations', 'late_checkout_status')) {
            Schema::table('reservations', function (Blueprint $table): void {
                $table->string('late_checkout_status', 16)->default('none')->after('status');
                $table->dateTime('late_checkout_until')->nullable()->after('late_checkout_status');
                $table->decimal('late_checkout_fee', 10, 2)->nullable()->after('late_checkout_until');
                $table->timestamp('late_checkout_requested_at')->nullable()->after('late_checkout_fee');
                $table->timestamp('late_checkout_decided_at')->nullable()->after('late_checkout_requested_at');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('reservation_pets');

        Schema::table('reservations', function (Blueprint $table): void {
            $table->dropColumn([
                'late_checkout_status',
                'late_checkout_until',
                'late_checkout_fee',
                'late_checkout_requested_at',
                'late_checkout_decided_at',
            ]);
        });

        Schema::table('hotels', function (Blueprint $table): void {
            $table->dropColumn(['extra_pet_fee', 'late_checkout_fee', 'late_checkout_hours']);
        });
    }
};
