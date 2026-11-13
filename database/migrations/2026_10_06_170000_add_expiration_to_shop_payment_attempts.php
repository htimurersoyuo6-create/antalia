<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void { Schema::table('shop_order_payment_attempts', function(Blueprint $table) { if(!Schema::hasColumn('shop_order_payment_attempts','expires_at')) $table->timestamp('expires_at')->nullable()->index(); }); }
 public function down(): void { Schema::table('shop_order_payment_attempts', function(Blueprint $table) { if(Schema::hasColumn('shop_order_payment_attempts','expires_at')) $table->dropColumn('expires_at'); }); }
};
