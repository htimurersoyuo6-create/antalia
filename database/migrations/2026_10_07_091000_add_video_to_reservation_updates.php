<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('reservation_updates') && !Schema::hasColumn('reservation_updates', 'video_path')) {
            Schema::table('reservation_updates', fn (Blueprint $table) => $table->string('video_path')->nullable()->after('photo_path'));
        }
    }

    public function down(): void
    {
        // Preserve guest care updates and their attached videos on rollback.
    }
};
