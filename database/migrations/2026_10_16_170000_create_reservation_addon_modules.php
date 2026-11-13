<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
return new class extends Migration {
    public function up(): void {
        Schema::create('grooming_options', function (Blueprint $t) {
            $t->id(); $t->foreignId('hotel_id')->nullable()->constrained()->nullOnDelete();
            $t->string('title',120); $t->decimal('price',10,2); $t->enum('pet_type',['cat','dog','both'])->default('both');
            $t->boolean('is_active')->default(true); $t->timestamps(); $t->index(['hotel_id','is_active']);
        });
        Schema::table('users', function(Blueprint $t){ $t->unsignedInteger('reward_points')->default(0); });
        Schema::table('reservations', function(Blueprint $t){
            $t->foreignId('grooming_option_id')->nullable()->constrained('grooming_options')->nullOnDelete();
            $t->decimal('grooming_fee',10,2)->default(0); $t->boolean('has_insurance')->default(false);
            $t->decimal('insurance_fee',10,2)->default(0); $t->decimal('insurance_provider_amount',10,2)->default(0);
            $t->unsignedInteger('points_redeemed')->default(0); $t->decimal('points_discount',10,2)->default(0);
        });
        Schema::create('reservation_transfers', function(Blueprint $t){
            $t->id(); $t->foreignId('reservation_id')->unique()->constrained()->cascadeOnDelete();
            $t->enum('transfer_type',['one_way_pickup','one_way_dropoff','round_trip']);
            $t->text('pickup_address')->nullable(); $t->text('dropoff_address')->nullable();
            $t->dateTime('pickup_datetime')->nullable(); $t->dateTime('dropoff_datetime')->nullable();
            $t->decimal('transfer_fee',10,2)->default(0);
            $t->enum('status',['pending','confirmed','completed','cancelled'])->default('pending');
            $t->timestamps(); $t->index(['status','pickup_datetime']);
        });
        Schema::create('point_transactions', function(Blueprint $t){
            $t->id(); $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('reservation_id')->nullable()->constrained()->nullOnDelete();
            $t->integer('points'); $t->enum('type',['earned','redeemed','expired']); $t->string('description',255); $t->timestamps();
            $t->index(['user_id','created_at']); $t->unique(['user_id','reservation_id','type']);
        });
        if (Schema::hasTable('reservation_updates') && !Schema::hasColumn('reservation_updates','mood')) {
            Schema::table('reservation_updates', fn(Blueprint $t)=>$t->string('mood',24)->nullable()->index());
        }
        DB::table('users')->whereNull('reward_points')->update(['reward_points'=>0]);
    }
    public function down(): void {
        if (Schema::hasTable('reservation_updates') && Schema::hasColumn('reservation_updates','mood')) Schema::table('reservation_updates',fn(Blueprint $t)=>$t->dropColumn('mood'));
        Schema::dropIfExists('point_transactions'); Schema::dropIfExists('reservation_transfers');
        Schema::table('reservations',function(Blueprint $t){$t->dropConstrainedForeignId('grooming_option_id');$t->dropColumn(['grooming_fee','has_insurance','insurance_fee','insurance_provider_amount','points_redeemed','points_discount']);});
        Schema::table('users',fn(Blueprint $t)=>$t->dropColumn('reward_points')); Schema::dropIfExists('grooming_options');
    }
};
