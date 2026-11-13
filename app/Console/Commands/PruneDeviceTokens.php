<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\DeviceToken;
use Illuminate\Console\Command;

class PruneDeviceTokens extends Command
{
    protected $signature = 'device-tokens:prune {--days=30}';

    protected $description = 'Revoked cihaz tokenlerini ve uzun süredir kullanılmayan tokenleri temizler';

    public function handle(): int
    {
        $days = max(7, (int) $this->option('days'));
        $cutoff = now()->subDays($days);

        $revoked = DeviceToken::query()->whereNotNull('revoked_at')->where('updated_at', '<', $cutoff)->delete();
        $stale = DeviceToken::query()->whereNull('revoked_at')->where('last_used_at', '<', $cutoff)->update(['revoked_at' => now()]);

        $this->info("silinen_revoked: {$revoked} · pasife_cevrilen_stale: {$stale}");

        return self::SUCCESS;
    }
}
