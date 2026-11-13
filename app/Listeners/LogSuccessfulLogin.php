<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Models\LoginHistory;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Http\Request;

class LogSuccessfulLogin
{
    public function __construct(private Request $request)
    {
    }

    public function handle(Login $event): void
    {
        try {
            LoginHistory::create([
                'user_id' => $event->user->id,
                'guard' => $event->guard,
                'ip' => $this->request->ip(),
                'user_agent' => mb_substr((string) $this->request->userAgent(), 0, 512),
                'successful' => true,
                'reason' => null,
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            // intentionally silent
        }
    }
}
