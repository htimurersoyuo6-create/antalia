<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
  if (!Schema::hasTable('hotel_room_units')) {
   Schema::create('hotel_room_units', function(Blueprint $t) {
    $t->id(); $t->foreignId('hotel_id')->constrained()->cascadeOnDelete();
    $t->foreignId('room_type_id')->constrained()->cascadeOnDelete();
    $t->string('name',80); $t->string('status',20)->default('available')->index();
    $t->string('note',300)->nullable(); $t->timestamps();
    $t->unique(['room_type_id','name']);
   });
   foreach(DB::table('room_types')->get() as $room) for($i=1;$i<=(int)$room->units;$i++)
    DB::table('hotel_room_units')->insert(['hotel_id'=>$room->hotel_id,'room_type_id'=>$room->id,'name'=>'Oda '.$i,'status'=>'available','created_at'=>now(),'updated_at'=>now()]);
  }
  if (!Schema::hasColumn('reservations','room_unit_id')) Schema::table('reservations', function(Blueprint $t) {
   $t->foreignId('room_unit_id')->nullable()->after('room_type_id')->constrained('hotel_room_units')->nullOnDelete();
  });
 }
 public function down(): void { /* Keep occupancy history and mappings. */ }
};
