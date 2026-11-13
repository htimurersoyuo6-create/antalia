<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\FcmPushService;
use App\Models\PetVaccination;
use App\Models\VaccinationCard;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class ScanVaccinationExpiry extends Command
{
    protected $signature = 'vaccinations:scan-expiry {--days=30}';

    protected $description = 'Aşı kartı ve aşı takvimindeki yaklaşan/geçmiş tarihleri tarar ve sahiplerine bildirim gönderir';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $threshold = now()->addDays($days)->toDateString();
        $notifiedCards = 0;
        $notifiedVaccinations = 0;

        VaccinationCard::query()
            ->with('pet.owner')
            ->whereNotNull('expires_at')
            ->whereDate('expires_at', '<=', $threshold)
            ->where('alerts_sent', false)
            ->chunkById(100, function ($cards) use (&$notifiedCards): void {
                foreach ($cards as $card) {
                    $owner = $card->pet?->owner;
                    if (! $owner) {
                        continue;
                    }
                    $this->push($owner, [
                        'title' => 'Aşı kartı süresi yaklaşıyor',
                        'body' => ($card->pet->name ?? 'Pets').' için aşı kartındaki geçerlilik tarihi '.$card->expires_at->format('d.m.Y').'. Lütfen güncel kartı yükleyin.',
                        'data' => ['type' => 'vaccination_expiry', 'card_id' => (string) $card->id],
                    ]);
                    $card->update(['alerts_sent' => true]);
                    $notifiedCards++;
                }
            });

        PetVaccination::query()
            ->with('pet.owner')
            ->where('reminder_enabled', true)
            ->whereNotNull('next_due_at')
            ->whereDate('next_due_at', '<=', $threshold)
            ->chunkById(100, function ($items) use (&$notifiedVaccinations): void {
                foreach ($items as $vaccination) {
                    $owner = $vaccination->pet?->owner;
                    if (! $owner) {
                        continue;
                    }
                    $this->push($owner, [
                        'title' => 'Aşı hatırlatması',
                        'body' => ($vaccination->pet->name ?? 'Pets').' için '.$vaccination->vaccine_name.' hatırlatması: '.$vaccination->next_due_at->format('d.m.Y'),
                        'data' => ['type' => 'vaccination_due', 'vaccination_id' => (string) $vaccination->id],
                    ]);
                    $notifiedVaccinations++;
                }
            });

        $this->info("asi_karti_uyari: {$notifiedCards}");
        $this->info("asi_takvimi_uyari: {$notifiedVaccinations}");

        return self::SUCCESS;
    }

    private function push($owner, array $message): void
    {
        try {
            FcmPushService::sendTo($owner, $message);
        } catch (Throwable $e) {
            Log::warning('vaccination.push_failed', ['user' => $owner->id, 'message' => $e->getMessage()]);
        }
    }
}
