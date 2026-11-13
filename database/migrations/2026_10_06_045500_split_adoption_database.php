<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
  if (!Schema::connection('adoption')->hasTable('adoption_listings')) {
   Schema::connection('adoption')->create('adoption_listings', function (Blueprint $t) {
    $t->id(); $t->unsignedBigInteger('user_id')->index(); $t->string('pet_name',100); $t->string('species',20); $t->string('breed',100)->nullable(); $t->unsignedSmallInteger('age_months')->nullable(); $t->string('sex',20)->nullable(); $t->boolean('vaccinated')->default(false); $t->string('city',100)->default('Antalya'); $t->string('district',100)->nullable(); $t->text('description'); $t->string('photo_path',255)->nullable(); $t->string('contact_phone',30)->nullable(); $t->string('status',20)->default('pending')->index(); $t->timestamp('approved_at')->nullable(); $t->timestamp('adopted_at')->nullable(); $t->timestamps(); $t->index(['species','city','status']);
   });
  }
  $main = DB::connection(config('database.default'));
  $adoption = DB::connection('adoption');
  if ($main->getSchemaBuilder()->hasTable('adoption_listings')) {
   $mainCount = $main->table('adoption_listings')->count();
   $main->table('adoption_listings')->orderBy('id')->chunkById(200, function ($rows) use ($adoption) {
    foreach ($rows as $row) { $data = (array) $row; $adoption->table('adoption_listings')->updateOrInsert(['id' => $row->id], $data); }
   });
   if ($adoption->table('adoption_listings')->count() < $mainCount) { throw new RuntimeException('Adoption data copy verification failed.'); }
   foreach ($main->table('adoption_listings')->orderBy('id')->pluck('id') as $id) {
    if (!$adoption->table('adoption_listings')->where('id', $id)->exists()) { throw new RuntimeException('Adoption ID verification failed.'); }
   }
   if (!$main->getSchemaBuilder()->hasTable('adoption_listings_legacy_20261006')) { $main->getSchemaBuilder()->rename('adoption_listings', 'adoption_listings_legacy_20261006'); }
  }
 }
 public function down(): void {
  $main = DB::connection(config('database.default'));
  $adoption = DB::connection('adoption');
  $schema = $main->getSchemaBuilder();

  if (!$schema->hasTable('adoption_listings')) {
   if ($schema->hasTable('adoption_listings_legacy_20261006')) {
    $schema->rename('adoption_listings_legacy_20261006', 'adoption_listings');
   } else {
    $schema->create('adoption_listings', function (Blueprint $t) {
     $t->id(); $t->foreignId('user_id')->constrained()->cascadeOnDelete(); $t->string('pet_name',100); $t->string('species',20); $t->string('breed',100)->nullable(); $t->unsignedSmallInteger('age_months')->nullable(); $t->string('sex',20)->nullable(); $t->boolean('vaccinated')->default(false); $t->string('city',100)->default('Antalya'); $t->string('district',100)->nullable(); $t->text('description'); $t->string('photo_path',255)->nullable(); $t->string('contact_phone',30)->nullable(); $t->string('status',20)->default('pending')->index(); $t->timestamp('approved_at')->nullable(); $t->timestamp('adopted_at')->nullable(); $t->timestamps(); $t->index(['species','city','status']);
    });
   }
  }

  if ($adoption->getSchemaBuilder()->hasTable('adoption_listings')) {
   $adoption->table('adoption_listings')->orderBy('id')->chunkById(200, function ($rows) use ($main) {
    foreach ($rows as $row) {
     if (!$main->table('users')->where('id', $row->user_id)->exists()) throw new RuntimeException('Cannot restore adoption listing with missing owner.');
     $main->table('adoption_listings')->updateOrInsert(['id' => $row->id], (array) $row);
    }
   });
   foreach ($adoption->table('adoption_listings')->orderBy('id')->pluck('id') as $id) {
    if (!$main->table('adoption_listings')->where('id', $id)->exists()) throw new RuntimeException('Adoption rollback verification failed.');
   }
  }
 }
};
