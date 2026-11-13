<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Minishlink\WebPush\VAPID;

return new class extends Migration
{
    public function up(): void
    {
        require_once app_path('Models/PushVendor/vendor/autoload.php');

        Schema::create('web_push_credentials', function (Blueprint $table): void {
            $table->unsignedTinyInteger('id')->primary();
            $table->string('public_key', 128);
            $table->text('private_key_encrypted');
            $table->string('subject', 255);
            $table->timestamps();
        });

        Schema::create('push_subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->char('endpoint_hash', 64)->unique();
            $table->text('endpoint_encrypted');
            $table->string('p256dh', 255);
            $table->text('auth_key_encrypted');
            $table->string('content_encoding', 32)->default('aes128gcm');
            $table->string('user_agent', 512)->nullable();
            $table->timestamp('last_sent_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'created_at']);
        });

        $keys = VAPID::createVapidKeys();
        DB::table('web_push_credentials')->insert([
            'id' => 1,
            'public_key' => $keys['publicKey'],
            'private_key_encrypted' => Crypt::encryptString($keys['privateKey']),
            'subject' => 'https://antalyapetotel.com',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('push_subscriptions');
        Schema::dropIfExists('web_push_credentials');
    }
};
