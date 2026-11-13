<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IotTelemetryLog extends Model
{
    protected $guarded = [];

    protected $casts = [
        'value' => 'decimal:2',
        'recorded_at' => 'datetime',
        'meta' => 'array',
    ];

    public function device()
    {
        return $this->belongsTo(IotDevice::class, 'iot_device_id');
    }

    public function hotel()
    {
        return $this->belongsTo(Hotel::class);
    }
}
