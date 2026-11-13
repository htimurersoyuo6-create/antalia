<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('geofenced_alerts', function (Blueprint $table): void {
            $table->boolean('is_public')->default(false)->after('status');
            $table->text('public_note')->nullable()->after('is_public');
            $table->string('public_contact_phone', 30)->nullable()->after('public_note');
            $table->index(['is_public', 'status', 'reported_at'], 'geofenced_alerts_public_status_reported_index');
        });
    }

    public function down(): void
    {
        if (DB::table('geofenced_alerts')->where('is_public', true)->exists()) {
            throw new \RuntimeException('Cannot remove public lost-pet fields while public reports may contain user-submitted data.');
        }

        Schema::table('geofenced_alerts', function (Blueprint $table): void {
            $table->dropIndex('geofenced_alerts_public_status_reported_index');
            $table->dropColumn(['is_public', 'public_note', 'public_contact_phone']);
        });
    }
};
