<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Models\LoginHistory;
use Illuminate\Auth\Events\Failed;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LogFailedLogin
{
    public function __construct(private Request $request)
    {
    }

    public function handle(Failed $event): void
    {
        try {
            $userId = is_object($event->user) ? ($event->user->id ?? null) : null;

            if (! $userId && isset($event->credentials['email'])) {
                $userId = DB::table('users')->where('email', $event->credentials['email'])->value('id');
            }

            if (! $userId) {
                return;
            }

            LoginHistory::create([
                'user_id' => $userId,
                'guard' => $event->guard,
                'ip' => $this->request->ip(),
                'user_agent' => mb_substr((string) $this->request->userAgent(), 0, 512),
                'successful' => false,
                'reason' => 'credentials',
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            // intentionally silent
        }
    }
}
