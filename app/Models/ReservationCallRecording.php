<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReservationCallRecording extends Model
{
    protected $guarded = [];
    protected $casts = ['started_at' => 'datetime', 'finished_at' => 'datetime', 'expires_at' => 'datetime'];
    public function call() { return $this->belongsTo(ReservationCall::class, 'call_id'); }
}
