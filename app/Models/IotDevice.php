<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IotDevice extends Model
{
    protected $guarded = [];

    protected $hidden = ['token_hash'];

    protected $casts = [
        'min_threshold' => 'decimal:2',
        'max_threshold' => 'decimal:2',
        'is_active' => 'boolean',
        'last_seen_at' => 'datetime',
    ];

    public function hotel()
    {
        return $this->belongsTo(Hotel::class);
    }

    public function roomUnit()
    {
        return $this->belongsTo(RoomUnit::class);
    }

    public function telemetry()
    {
        return $this->hasMany(IotTelemetryLog::class);
    }

    public function isBreaching(float $value): bool
    {
        if ($this->min_threshold !== null && $value < (float) $this->min_threshold) {
            return true;
        }

        return $this->max_threshold !== null && $value > (float) $this->max_threshold;
    }
}
