<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RevenuePricingRule extends Model
{
    protected $table = 'pricing_rules';

    protected $guarded = [];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'min_nights' => 'integer',
        'base_multiplier_percent' => 'integer',
        'surcharge_percent' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function hotel()
    {
        return $this->belongsTo(Hotel::class);
    }
}
