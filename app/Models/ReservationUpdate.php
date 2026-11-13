<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReservationUpdate extends Model
{
    protected $guarded = [];

    public function reservation()
    {
        return $this->belongsTo(Reservation::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
