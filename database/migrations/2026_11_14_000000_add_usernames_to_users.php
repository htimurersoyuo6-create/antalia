<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $addedUsernameColumn = ! Schema::hasColumn('users', 'username');
        if ($addedUsernameColumn) {
            Schema::table('users', function (Blueprint $table): void {
                $table->string('username', 50)->nullable();
            });
        }

        DB::table('users')
            ->select(['id', 'name'])
            ->where(function ($query): void {
                $query->whereNull('username')->orWhere('username', '');
            })
            ->chunkById(500, function ($users): void {
                foreach ($users as $user) {
                    $base = Str::substr(Str::slug((string) $user->name) ?: 'uye', 0, 46);
                    $username = $base;
                    $suffix = 2;

                    while (DB::table('users')->where('username', $username)->exists()) {
                        $username = $base.'-'.$suffix++;
                    }

                    DB::table('users')->where('id', $user->id)->update(['username' => $username]);
                }
            });

        if ($addedUsernameColumn) {
            Schema::table('users', function (Blueprint $table): void {
                $table->unique('username');
            });
        }
    }

    public function down(): void
    {
        // Existing profile URLs may rely on a username column created outside this migration.
    }
};
