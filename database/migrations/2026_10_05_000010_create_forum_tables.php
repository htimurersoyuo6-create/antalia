<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
  Schema::create('forum_topics', function(Blueprint $t){$t->id();$t->foreignId('user_id')->constrained()->cascadeOnDelete();$t->string('category',40)->index();$t->string('title',180);$t->text('body');$t->string('slug')->unique();$t->string('status',20)->default('pending')->index();$t->unsignedInteger('views')->default(0);$t->timestamp('publish_at')->nullable()->index();$t->timestamps();$t->index(['status','created_at']);});
  Schema::create('forum_slug_redirects', function(Blueprint $t){$t->id();$t->string('old_slug')->unique();$t->foreignId('forum_topic_id')->constrained('forum_topics')->cascadeOnDelete();$t->timestamps();});
  Schema::create('forum_replies', function(Blueprint $t){$t->id();$t->foreignId('forum_topic_id')->constrained()->cascadeOnDelete();$t->foreignId('user_id')->constrained()->cascadeOnDelete();$t->text('body');$t->string('status',20)->default('pending')->index();$t->timestamps();});
 }
 public function down(): void { Schema::dropIfExists('forum_slug_redirects'); Schema::dropIfExists('forum_replies'); Schema::dropIfExists('forum_topics'); }
};
