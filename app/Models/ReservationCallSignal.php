<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReservationCallSignal extends Model
{
    public $timestamps = false;
    protected $guarded = [];
    protected $casts = ['created_at' => 'datetime'];

    public function call() { return $this->belongsTo(ReservationCall::class, 'call_id'); }
}
