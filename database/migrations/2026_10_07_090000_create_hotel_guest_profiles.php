<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('hotel_guest_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('guest_name', 120);
            $table->string('guest_phone', 30)->nullable();
            $table->string('guest_email', 160)->nullable();
            $table->string('guest_address', 500)->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
            $table->unique(['hotel_id', 'user_id'], 'hotel_guest_profiles_hotel_user_unique');
            $table->index(['hotel_id', 'last_seen_at']);
        });

        DB::table('reservations')->select('hotel_id', 'user_id')
            ->selectRaw('MIN(created_at) as first_seen_at, MAX(created_at) as last_seen_at')
            ->groupBy('hotel_id', 'user_id')->orderBy('hotel_id')->orderBy('user_id')
            ->chunk(500, function ($pairs) {
                $users = DB::table('users')->whereIn('id', $pairs->pluck('user_id')->unique())
                    ->get(['id', 'name', 'phone', 'email', 'address'])->keyBy('id');
                $now = now();
                $rows = [];
                foreach ($pairs as $pair) {
                    $user = $users->get($pair->user_id);
                    if (!$user) continue;
                    $rows[] = [
                        'hotel_id' => $pair->hotel_id, 'user_id' => $pair->user_id,
                        'guest_name' => $user->name, 'guest_phone' => $user->phone,
                        'guest_email' => $user->email, 'guest_address' => $user->address,
                        'first_seen_at' => $pair->first_seen_at, 'last_seen_at' => $pair->last_seen_at,
                        'created_at' => $now, 'updated_at' => $now,
                    ];
                }
                if ($rows) DB::table('hotel_guest_profiles')->insertOrIgnore($rows);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('hotel_guest_profiles');
    }
};
