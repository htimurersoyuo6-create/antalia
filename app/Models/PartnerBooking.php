<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PartnerBooking extends Model
{
    protected $guarded = [];

    protected $casts = [
        'payload' => 'array',
        'total_cents' => 'integer',
        'commission_cents' => 'integer',
    ];

    public function partnerHotel()
    {
        return $this->belongsTo(PartnerHotel::class);
    }

    public function reservation()
    {
        return $this->belongsTo(Reservation::class);
    }
}
