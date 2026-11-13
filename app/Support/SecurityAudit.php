<?php

namespace App\Support;

use App\Models\SecurityEvent;
use Illuminate\Http\Request;

class SecurityAudit
{
    public static function record(Request $request, string $event, string $result = 'success', ?int $userId = null, ?string $action = null, ?array $details = null, ?int $actorId = null): void
    {
        try {
            SecurityEvent::create([
                'user_id' => $userId,
                'actor_id' => $actorId,
                'event_type' => $event,
                'result' => $result,
                'action' => $action,
                'ip_address' => $request->ip(),
                'user_agent' => mb_substr((string) $request->userAgent(), 0, 500),
                'details' => $details,
                'occurred_at' => now(),
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public static function failedLogin(Request $request, ?int $userId, string $identifier, string $result = 'failed'): void
    {
        $hint = filter_var($identifier, FILTER_VALIDATE_EMAIL)
            ? (mb_substr($identifier, 0, 1) . '***@' . (explode('@', $identifier)[1] ?? ''))
            : ('***' . substr(preg_replace('/\D+/', '', $identifier), -3));
        try {
            \App\Models\SecurityEvent::create([
                'user_id' => $userId, 'event_type' => 'login', 'result' => $result,
                'account_hint' => mb_substr($hint, 0, 100), 'ip_address' => $request->ip(),
                'user_agent' => mb_substr((string) $request->userAgent(), 0, 500), 'occurred_at' => now(),
            ]);
        } catch (\Throwable $e) { report($e); }
    }
}
