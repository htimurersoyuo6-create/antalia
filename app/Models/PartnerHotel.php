<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PartnerHotel extends Model
{
    protected $guarded = [];

    protected $hidden = ['api_key_hash'];

    protected $casts = [
        'commission_rate' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function bookings()
    {
        return $this->hasMany(PartnerBooking::class);
    }

    public function reservations()
    {
        return $this->hasMany(Reservation::class);
    }
}
