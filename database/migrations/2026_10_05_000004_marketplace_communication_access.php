<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
  if (Schema::hasTable('hotels')) Schema::table('hotels', function(Blueprint $t) {
   if (!Schema::hasColumn('hotels','camera_enabled')) $t->boolean('camera_enabled')->default(false);
   if (!Schema::hasColumn('hotels','camera_embed_url')) $t->text('camera_embed_url')->nullable();
  });
  if (Schema::hasTable('users')) Schema::table('users', function(Blueprint $t) {
   if (!Schema::hasColumn('users','archived_at')) $t->timestamp('archived_at')->nullable()->index();
   if (!Schema::hasColumn('users','blacklisted_at')) $t->timestamp('blacklisted_at')->nullable()->index();
   if (!Schema::hasColumn('users','blacklist_reason')) $t->string('blacklist_reason',500)->nullable();
  });
 }
 public function down(): void { /* retain production access history */ }
};
