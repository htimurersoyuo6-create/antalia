<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReservationAddonItem extends Model
{
    protected $guarded = [];
    protected $casts = ['unit_price' => 'decimal:2', 'line_total' => 'decimal:2'];
    public function order() { return $this->belongsTo(ReservationAddonOrder::class, 'order_id'); }
    public function hotelExtra() { return $this->belongsTo(HotelExtra::class); }
}
