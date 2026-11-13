<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DemandForecast extends Model
{
    protected $guarded = [];

    protected $casts = [
        'forecast_date' => 'date',
        'occupancy_rate' => 'decimal:2',
        'booking_pace' => 'decimal:2',
        'season_index' => 'decimal:2',
        'suggested_multiplier' => 'decimal:2',
        'confidence' => 'decimal:2',
        'generated_at' => 'datetime',
    ];

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }
}
