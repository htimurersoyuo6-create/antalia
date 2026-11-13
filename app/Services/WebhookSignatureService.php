<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\WebhookEvent;
use Illuminate\Support\Facades\Log;
use Throwable;

class WebhookSignatureService
{
    public function secretFor(string $provider): string
    {
        return (string) config('services.webhooks.'.$provider.'.secret', '');
    }

    public function headerFor(string $provider): string
    {
        return (string) config('services.webhooks.'.$provider.'.header', 'X-Webhook-Signature');
    }

    public function verify(string $provider, string $rawBody, ?string $signature): bool
    {
        $secret = $this->secretFor($provider);

        if ($secret === '' || ! $signature) {
            return false;
        }

        $signature = trim($signature);

        if (str_starts_with($signature, 'sha256=')) {
            $signature = substr($signature, 7);
        }

        $expected = hash_hmac('sha256', $rawBody, $secret);

        return hash_equals($expected, $signature);
    }

    public function record(string $provider, string $rawBody, ?string $signature, bool $valid): WebhookEvent
    {
        $payload = json_decode($rawBody, true);
        $payload = is_array($payload) ? $payload : ['raw' => mb_substr($rawBody, 0, 5000)];

        $externalId = $this->externalId($provider, $payload);

        try {
            return WebhookEvent::create([
                'provider' => $provider,
                'event_type' => $this->eventType($provider, $payload),
                'external_id' => $externalId,
                'payload' => $payload,
                'signature_valid' => $valid,
                'status' => $valid ? 'received' : 'rejected',
                'attempts' => 0,
            ]);
        } catch (Throwable $e) {
            Log::warning('webhook.record_failed', ['provider' => $provider, 'message' => $e->getMessage()]);

            return WebhookEvent::query()
                ->where('provider', $provider)
                ->where('external_id', $externalId)
                ->firstOrFail();
        }
    }

    private function externalId(string $provider, array $payload): string
    {
        $candidates = [
            'id', 'event_id', 'paymentId', 'payment_id', 'conversationId',
            'message_id', 'messageId', 'jobid', 'job_id',
        ];

        foreach ($candidates as $key) {
            if (isset($payload[$key]) && is_scalar($payload[$key])) {
                return $provider.':'.mb_substr((string) $payload[$key], 0, 100);
            }
        }

        return $provider.':'.sha1(json_encode($payload));
    }

    private function eventType(string $provider, array $payload): ?string
    {
        foreach (['event_type', 'eventType', 'type', 'status', 'notification_type'] as $key) {
            if (isset($payload[$key]) && is_scalar($payload[$key])) {
                return mb_substr((string) $payload[$key], 0, 80);
            }
        }

        return null;
    }
}
