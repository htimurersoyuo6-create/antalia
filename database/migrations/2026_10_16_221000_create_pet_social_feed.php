<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('pet_social_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pet_id')->nullable()->constrained()->nullOnDelete();
            $table->string('photo_path', 255);
            $table->string('caption', 500)->nullable();
            $table->timestamp('public_consent_at');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->foreignId('moderated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('moderated_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });
        Schema::create('pet_social_post_likes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pet_social_post_id')->constrained('pet_social_posts')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['pet_social_post_id', 'user_id']);
        });
        Schema::create('pet_social_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pet_social_post_id')->constrained('pet_social_posts')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('body', 500);
            $table->timestamps();
            $table->index(['pet_social_post_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pet_social_comments');
        Schema::dropIfExists('pet_social_post_likes');
        Schema::dropIfExists('pet_social_posts');
    }
};
