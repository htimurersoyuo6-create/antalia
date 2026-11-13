<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void { Schema::connection('adoption')->create('adoption_listings', function(Blueprint $t) { $t->id(); $t->unsignedBigInteger('user_id')->index(); $t->string('pet_name',100); $t->string('species',20); $t->string('breed',100)->nullable(); $t->unsignedSmallInteger('age_months')->nullable(); $t->string('sex',20)->nullable(); $t->boolean('vaccinated')->default(false); $t->string('city',100)->default('Antalya'); $t->string('district',100)->nullable(); $t->text('description'); $t->string('contact_phone',30)->nullable(); $t->string('status',20)->default('pending')->index(); $t->timestamp('approved_at')->nullable(); $t->timestamp('adopted_at')->nullable(); $t->timestamps(); $t->index(['species','city','status']); }); }
 public function down(): void { Schema::connection('adoption')->dropIfExists('adoption_listings'); }
};
