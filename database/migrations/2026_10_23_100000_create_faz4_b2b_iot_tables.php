<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('partner_hotels')) {
            Schema::create('partner_hotels', function (Blueprint $table): void {
                $table->id();
                $table->string('name', 160);
                $table->string('contact_name', 120)->nullable();
                $table->string('email', 160)->nullable();
                $table->string('phone', 30)->nullable();
                $table->string('city', 80)->default('Antalya');
                $table->string('address', 255)->nullable();
                $table->decimal('commission_rate', 5, 2)->default(10.00);
                $table->char('api_key_hash', 64)->unique();
                $table->string('api_key_last4', 8);
                $table->boolean('is_active')->default(true);
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->index(['is_active', 'city']);
            });
        }

        if (! Schema::hasTable('partner_bookings')) {
            Schema::create('partner_bookings', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('partner_hotel_id')->constrained()->cascadeOnDelete();
                $table->foreignId('reservation_id')->nullable()->constrained()->nullOnDelete();
                $table->string('external_ref', 80)->nullable()->unique();
                $table->string('status', 20)->default('pending');
                $table->unsignedInteger('total_cents')->default(0);
                $table->unsignedInteger('commission_cents')->default(0);
                $table->json('payload')->nullable();
                $table->timestamps();
                $table->index(['partner_hotel_id', 'status']);
            });
        }

        if (! Schema::hasTable('iot_devices')) {
            Schema::create('iot_devices', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
                $table->foreignId('room_unit_id')->nullable()->constrained()->nullOnDelete();
                $table->string('name', 120);
                $table->string('device_uid', 80)->unique();
                $table->string('token_hash', 64);
                $table->string('type', 24)->default('temperature');
                $table->string('mqtt_topic', 160)->nullable();
                $table->decimal('min_threshold', 8, 2)->nullable();
                $table->decimal('max_threshold', 8, 2)->nullable();
                $table->string('unit', 16)->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamp('last_seen_at')->nullable();
                $table->timestamps();
                $table->index(['hotel_id', 'type', 'is_active']);
            });
        }

        if (! Schema::hasTable('iot_telemetry_logs')) {
            Schema::create('iot_telemetry_logs', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('iot_device_id')->constrained()->cascadeOnDelete();
                $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
                $table->string('metric', 32);
                $table->decimal('value', 10, 2);
                $table->string('unit', 16)->nullable();
                $table->timestamp('recorded_at');
                $table->json('meta')->nullable();
                $table->timestamps();
                $table->index(['iot_device_id', 'recorded_at']);
                $table->index(['hotel_id', 'metric', 'recorded_at']);
            });
        }

        if (! Schema::hasColumn('reservations', 'source')) {
            Schema::table('reservations', function (Blueprint $table): void {
                $table->string('source', 20)->default('web')->after('payment_method');
                $table->foreignId('partner_hotel_id')->nullable()->after('source')->constrained('partner_hotels')->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('pet_transfers', 'flight_number')) {
            Schema::table('pet_transfers', function (Blueprint $table): void {
                $table->string('flight_number', 12)->nullable()->after('fee');
                $table->string('flight_status', 24)->nullable()->after('flight_number');
                $table->timestamp('flight_checked_at')->nullable()->after('flight_status');
            });
        }
    }

    public function down(): void
    {
        Schema::table('pet_transfers', function (Blueprint $table): void {
            $table->dropColumn(['flight_number', 'flight_status', 'flight_checked_at']);
        });
        Schema::table('reservations', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('partner_hotel_id');
            $table->dropColumn('source');
        });
        Schema::dropIfExists('iot_telemetry_logs');
        Schema::dropIfExists('iot_devices');
        Schema::dropIfExists('partner_bookings');
        Schema::dropIfExists('partner_hotels');
    }
};
