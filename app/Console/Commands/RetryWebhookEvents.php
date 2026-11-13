<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\ProcessWebhookEvent;
use App\Models\WebhookEvent;
use Illuminate\Console\Command;

class RetryWebhookEvents extends Command
{
    protected $signature = 'webhooks:retry {--limit=100}';

    protected $description = 'İmzası geçerli ancak işlenememiş webhook olaylarını kuyruğa yeniden gönderir';

    public function handle(): int
    {
        $limit = max(1, (int) $this->option('limit'));

        $events = WebhookEvent::query()
            ->where('signature_valid', true)
            ->where('status', 'failed')
            ->where('attempts', '<', 5)
            ->orderBy('id')
            ->limit($limit)
            ->get();

        foreach ($events as $event) {
            ProcessWebhookEvent::dispatch($event->id);
        }

        $this->info('yeniden_kuyruga: '.$events->count());

        return self::SUCCESS;
    }
}
