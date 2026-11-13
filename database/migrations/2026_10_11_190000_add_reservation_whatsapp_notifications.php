<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
  Schema::table('whatsapp_cloud_settings', function(Blueprint $t){
   $t->string('reservation_hotel_template',100)->nullable();
   $t->string('reservation_customer_template',100)->nullable();
   $t->string('reservation_confirmed_template',100)->nullable();
  });
  Schema::table('reservations', function(Blueprint $t){ $t->timestamp('whatsapp_consent_at')->nullable()->after('customer_note'); });
  Schema::create('reservation_whatsapp_notifications', function(Blueprint $t){
   $t->id(); $t->foreignId('reservation_id')->constrained()->cascadeOnDelete();
   $t->string('event',50); $t->string('recipient_type',20); $t->string('recipient',20);
   $t->string('status',20)->default('pending'); $t->string('message_id',150)->nullable();
   $t->text('error')->nullable(); $t->timestamp('sent_at')->nullable(); $t->timestamps();
   $t->unique(['reservation_id','event','recipient_type'],'res_wa_event_recipient_unique');
  });
 }
 public function down(): void {
  Schema::dropIfExists('reservation_whatsapp_notifications');
  Schema::table('reservations',fn(Blueprint $t)=>$t->dropColumn('whatsapp_consent_at'));
  Schema::table('whatsapp_cloud_settings',fn(Blueprint $t)=>$t->dropColumn(['reservation_hotel_template','reservation_customer_template','reservation_confirmed_template']));
 }
};
