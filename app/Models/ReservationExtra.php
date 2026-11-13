<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReservationExtra extends Model
{
    public $timestamps = false;
    protected $guarded = [];
    public function reservation() { return $this->belongsTo(Reservation::class); }
}
