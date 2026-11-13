<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\SecurityEvent;
use App\Models\WebhookEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessWebhookEvent implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public array $backoff = [10, 30, 60, 300, 900];

    public function __construct(public int $webhookEventId)
    {
    }

    public function handle(): void
    {
        $event = WebhookEvent::query()->find($this->webhookEventId);

        if (! $event || ! $event->signature_valid) {
            return;
        }

        try {
            $handlerClass = (string) config('services.webhooks.'.$event->provider.'.handler', '');

            if ($handlerClass !== '' && class_exists($handlerClass)) {
                app($handlerClass)->handle($event);
            }

            $event->markProcessed();

            SecurityEvent::create([
                'user_id' => null,
                'actor_id' => null,
                'event_type' => 'webhook_processed',
                'result' => 'success',
                'action' => mb_substr('webhook '.$event->provider.' '.$event->event_type, 0, 180),
                'resource_type' => 'WebhookEvent',
                'resource_id' => (string) $event->id,
                'ip_address' => null,
                'user_agent' => null,
                'details' => ['provider' => $event->provider, 'event_type' => $event->event_type],
                'occurred_at' => now(),
            ]);
        } catch (Throwable $e) {
            $event->markFailed($e->getMessage());
            Log::error('webhook.process_failed', ['event' => $event->id, 'provider' => $event->provider, 'message' => $e->getMessage()]);

            throw $e;
        }
    }
}
