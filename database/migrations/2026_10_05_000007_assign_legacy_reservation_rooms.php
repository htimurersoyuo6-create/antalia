<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
return new class extends Migration {
 public function up(): void {
  DB::table('reservations')->whereIn('status',['pending','confirmed'])->whereNull('room_unit_id')->orderBy('check_in')->orderBy('id')->chunkById(100,function($reservations) {
   foreach($reservations as $r) {
    $unit=DB::table('hotel_room_units as u')->where('u.room_type_id',$r->room_type_id)->where('u.status','available')
      ->whereNotExists(function($q) use($r) {
       $q->selectRaw('1')->from('reservations as x')->whereColumn('x.room_unit_id','u.id')
        ->whereIn('x.status',['pending','confirmed'])->where('x.check_in','<',$r->check_out)->where('x.check_out','>',$r->check_in);
      })->orderBy('u.id')->value('u.id');
    if($unit) DB::table('reservations')->where('id',$r->id)->whereNull('room_unit_id')->update(['room_unit_id'=>$unit]);
   }
  });
 }
 public function down(): void {}
};
