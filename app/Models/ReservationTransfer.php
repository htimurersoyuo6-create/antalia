<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ReservationTransfer extends Model
{
    protected $guarded=[];
    protected $casts=['pickup_datetime'=>'datetime','dropoff_datetime'=>'datetime','transfer_fee'=>'decimal:2','distance_km'=>'decimal:2'];
    public function reservation(){return $this->belongsTo(Reservation::class);}
}
