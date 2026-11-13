<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HotelGuestProfile extends Model
{
    protected $guarded = [];
    protected $casts = ['first_seen_at' => 'datetime', 'last_seen_at' => 'datetime'];

    public function hotel() { return $this->belongsTo(Hotel::class); }
    public function user() { return $this->belongsTo(User::class); }
}
