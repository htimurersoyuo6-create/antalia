<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
return new class extends Migration {
    public function up(): void {
        if (!Schema::hasTable('pet_transfers')) return;
        DB::table('pet_transfers')->orderBy('id')->chunk(500,function($rows){foreach($rows as $old){
            if (DB::table('reservation_transfers')->where('reservation_id',$old->reservation_id)->exists()) continue;
            $res=DB::table('reservations')->where('id',$old->reservation_id)->first(); if(!$res) continue;
            $type=($old->transfer_type??$res->transfer_type)==='round_trip'?'round_trip':'one_way_pickup';
            $status=match($old->status??'requested'){'completed'=>'completed','cancelled'=>'cancelled','in_progress'=>'confirmed',default=>'pending'};
            DB::table('reservation_transfers')->insert(['reservation_id'=>$old->reservation_id,'transfer_type'=>$type,'pickup_address'=>$old->pickup_address??$res->transfer_pickup_address,'dropoff_address'=>$old->dropoff_address??(($type==='round_trip')?($res->transfer_return_address??''):(DB::table('hotels')->where('id',$res->hotel_id)->value('address')??'')),'pickup_datetime'=>$old->pickup_at??null,'dropoff_datetime'=>null,'transfer_fee'=>$old->fee??$res->transfer_total??0,'status'=>$status,'created_at'=>$old->created_at??now(),'updated_at'=>$old->updated_at??now()]);
        }});
    }
    public function down(): void {}
};
