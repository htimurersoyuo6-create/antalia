<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WebhookEvent extends Model
{
    protected $guarded = [];

    protected $casts = [
        'payload' => 'array',
        'signature_valid' => 'boolean',
        'attempts' => 'integer',
        'processed_at' => 'datetime',
    ];

    public function scopePending($query)
    {
        return $query->whereIn('status', ['received', 'failed']);
    }

    public function markProcessed(): void
    {
        $this->update(['status' => 'processed', 'processed_at' => now(), 'last_error' => null]);
    }

    public function markFailed(string $error): void
    {
        $this->update([
            'status' => 'failed',
            'attempts' => $this->attempts + 1,
            'last_error' => mb_substr($error, 0, 2000),
        ]);
    }
}
