<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class PetTransfer extends Model
{
    protected $guarded = [];
    protected $casts = ['pickup_at' => 'datetime', 'distance_km' => 'decimal:2', 'fee' => 'decimal:2'];
    public function reservation() { return $this->belongsTo(Reservation::class); }
    public function hotel() { return $this->belongsTo(Hotel::class); }
}
