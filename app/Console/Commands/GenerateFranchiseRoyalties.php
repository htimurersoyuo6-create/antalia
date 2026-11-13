<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\FranchiseRoyaltyService;
use Illuminate\Console\Command;

class GenerateFranchiseRoyalties extends Command
{
    protected $signature = 'franchise:royalties {--period= : YYYY-MM (varsayılan: geçen ay)} {--issue : Bildirimleri kes}';

    protected $description = 'Franchise birimleri için dönemsel royalti ve pazarlama payı bildirimlerini üretir';

    public function handle(FranchiseRoyaltyService $royalties): int
    {
        $period = (string) ($this->option('period') ?: now()->subMonthNoOverflow()->format('Y-m'));
        $results = $royalties->runAll($period, (bool) $this->option('issue'));

        foreach ($results as $groupId => $result) {
            $this->line('grup '.$groupId.': yeni '.$result['created'].', guncellenen '.$result['updated']);
        }

        return self::SUCCESS;
    }
}
