<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PartnerSettlement extends Model
{
    protected $guarded = [];

    protected $casts = [
        'booking_count' => 'integer',
        'gross_cents' => 'integer',
        'commission_cents' => 'integer',
        'net_cents' => 'integer',
        'issued_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    public function partnerHotel(): BelongsTo
    {
        return $this->belongsTo(PartnerHotel::class);
    }
}
