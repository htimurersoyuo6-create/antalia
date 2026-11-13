<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\PartnerHotel;
use App\Services\PartnerSettlementService;
use Illuminate\Console\Command;
use Throwable;

class SettlePartnerCommissions extends Command
{
    protected $signature = 'partner:settle {--period= : YYYY-MM (varsayılan: geçen ay)} {--partner= : Yalnızca bu partner otel kimliği} {--issue : Bildirimleri kes}';

    protected $description = 'Partner otel rezervasyonları için dönemsel komisyon mutabakatı üretir';

    public function handle(PartnerSettlementService $settlements): int
    {
        $period = (string) ($this->option('period') ?: now()->subMonthNoOverflow()->format('Y-m'));
        $issue = (bool) $this->option('issue');
        $partnerId = $this->option('partner');
        $done = 0;
        $failed = 0;

        if ($partnerId) {
            $partner = PartnerHotel::query()->findOrFail((int) $partnerId);

            try {
                $settlements->generate($partner, $period, $issue);
                $done++;
            } catch (Throwable $e) {
                $this->error($e->getMessage());
                $failed++;
            }
        } else {
            foreach ($settlements->generateAll($period, $issue) as $settlement) {
                $done++;
                $this->line($settlement->partner_hotel_id.' · '.$settlement->period.' · '.$settlement->status);
            }
        }

        $this->info("mutabakat: {$done} · hata: {$failed}");

        return self::SUCCESS;
    }
}
