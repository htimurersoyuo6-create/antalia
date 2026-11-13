<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HotelDiscount extends Model
{
    protected $guarded = [];
    protected $casts = ['active' => 'boolean'];
    public function hotel() { return $this->belongsTo(Hotel::class); }
}
