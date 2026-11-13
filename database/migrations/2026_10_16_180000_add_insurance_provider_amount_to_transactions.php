<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void { Schema::table('transactions',fn(Blueprint $t)=>$t->decimal('insurance_provider_amount',12,2)->default(0)->after('hotel_payout')); }
    public function down(): void { Schema::table('transactions',fn(Blueprint $t)=>$t->dropColumn('insurance_provider_amount')); }
};
