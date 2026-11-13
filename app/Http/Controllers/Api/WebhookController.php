<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessWebhookEvent;
use App\Services\WebhookSignatureService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    public function receive(Request $request, string $provider, WebhookSignatureService $signatures): JsonResponse
    {
        $allowed = array_keys((array) config('services.webhooks', []));

        if (! in_array($provider, $allowed, true)) {
            return response()->json(['error' => 'unknown_provider'], 404);
        }

        $raw = $request->getContent();
        $header = $signatures->headerFor($provider);
        $signature = $request->header($header) ?: $request->header('X-Hub-Signature-256');
        $valid = $signatures->verify($provider, $raw, $signature);

        $event = $signatures->record($provider, $raw, $signature, $valid);

        if (! $valid) {
            Log::warning('webhook.invalid_signature', ['provider' => $provider, 'event' => $event->id]);

            return response()->json(['error' => 'invalid_signature'], 401);
        }

        ProcessWebhookEvent::dispatch($event->id);

        return response()->json(['received' => true, 'event_id' => $event->id]);
    }
}
