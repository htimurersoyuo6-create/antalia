<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReservationCameraAccess extends Model
{
    protected $guarded = [];
    protected $casts = ['enabled' => 'boolean', 'enabled_at' => 'datetime', 'disabled_at' => 'datetime'];

    public function reservation() { return $this->belongsTo(Reservation::class); }
}
