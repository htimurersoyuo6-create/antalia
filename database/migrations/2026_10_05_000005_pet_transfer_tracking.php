<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
  Schema::table('hotels', function(Blueprint $t) {
   if (!Schema::hasColumn('hotels','transfer_enabled')) $t->boolean('transfer_enabled')->default(false);
   if (!Schema::hasColumn('hotels','transfer_mode')) $t->string('transfer_mode',12)->default('free');
   if (!Schema::hasColumn('hotels','transfer_price_km')) $t->decimal('transfer_price_km',10,2)->default(0);
   if (!Schema::hasColumn('hotels','transfer_price_pet')) $t->decimal('transfer_price_pet',10,2)->default(0);
   if (!Schema::hasColumn('hotels','transfer_instructions')) $t->string('transfer_instructions',500)->nullable();
  });
  Schema::table('reservations', function(Blueprint $t) {
   if (!Schema::hasColumn('reservations','transfer_status')) $t->string('transfer_status',20)->default('not_requested')->index();
   if (!Schema::hasColumn('reservations','transfer_type')) $t->string('transfer_type',20)->nullable();
   if (!Schema::hasColumn('reservations','transfer_pickup_address')) $t->string('transfer_pickup_address',500)->nullable();
   if (!Schema::hasColumn('reservations','transfer_return_address')) $t->string('transfer_return_address',500)->nullable();
   if (!Schema::hasColumn('reservations','transfer_pickup_lat')) $t->decimal('transfer_pickup_lat',10,7)->nullable();
   if (!Schema::hasColumn('reservations','transfer_pickup_lng')) $t->decimal('transfer_pickup_lng',10,7)->nullable();
   if (!Schema::hasColumn('reservations','transfer_return_lat')) $t->decimal('transfer_return_lat',10,7)->nullable();
   if (!Schema::hasColumn('reservations','transfer_return_lng')) $t->decimal('transfer_return_lng',10,7)->nullable();
   if (!Schema::hasColumn('reservations','transfer_estimated_km')) $t->decimal('transfer_estimated_km',7,2)->nullable();
   if (!Schema::hasColumn('reservations','transfer_total')) $t->decimal('transfer_total',10,2)->default(0);
   if (!Schema::hasColumn('reservations','transfer_lat')) $t->decimal('transfer_lat',10,7)->nullable();
   if (!Schema::hasColumn('reservations','transfer_lng')) $t->decimal('transfer_lng',10,7)->nullable();
   if (!Schema::hasColumn('reservations','transfer_location_shared_at')) $t->timestamp('transfer_location_shared_at')->nullable();
   if (!Schema::hasColumn('reservations','transfer_started_at')) $t->timestamp('transfer_started_at')->nullable();
   if (!Schema::hasColumn('reservations','transfer_completed_at')) $t->timestamp('transfer_completed_at')->nullable();
  });
 }
 public function down(): void { /* Keep live transfer records intact. */ }
};
