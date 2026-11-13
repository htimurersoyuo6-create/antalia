<?php
use Illuminate\Database\Migrations\Migration;use Illuminate\Database\Schema\Blueprint;use Illuminate\Support\Facades\Schema;
return new class extends Migration{
 public function up():void{
  if(Schema::hasTable('reservation_transfers')&&!Schema::hasColumn('reservation_transfers','distance_km'))Schema::table('reservation_transfers',fn(Blueprint $t)=>$t->decimal('distance_km',8,2)->nullable()->after('dropoff_datetime'));
  if(Schema::hasTable('reservation_transfers')&&!Schema::hasColumn('reservation_transfers','vehicle_type'))Schema::table('reservation_transfers',fn(Blueprint $t)=>$t->string('vehicle_type',24)->nullable()->after('distance_km'));
  if(!Schema::hasTable('transfer_zone_rates'))Schema::create('transfer_zone_rates',function(Blueprint $t){$t->id();$t->foreignId('hotel_id')->nullable()->constrained()->cascadeOnDelete();$t->string('from_zone',120);$t->string('to_zone',120);$t->decimal('fee',10,2);$t->boolean('is_active')->default(true);$t->timestamps();$t->unique(['hotel_id','from_zone','to_zone']);$t->index(['is_active','from_zone','to_zone']);});
 }
 public function down():void{Schema::dropIfExists('transfer_zone_rates');if(Schema::hasColumn('reservation_transfers','vehicle_type'))Schema::table('reservation_transfers',fn(Blueprint $t)=>$t->dropColumn('vehicle_type'));
  if(Schema::hasColumn('reservation_transfers','distance_km'))Schema::table('reservation_transfers',fn(Blueprint $t)=>$t->dropColumn('distance_km'));}
};
