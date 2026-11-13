<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HotelFeatureFlag extends Model
{
    protected $guarded = [];
    protected $casts = ['enabled' => 'boolean'];
    public function hotel() { return $this->belongsTo(Hotel::class); }
}
