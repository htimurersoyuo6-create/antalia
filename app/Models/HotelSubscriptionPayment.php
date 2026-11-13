<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HotelSubscriptionPayment extends Model
{
    protected $guarded = [];
    protected $casts = ['amount_kurus' => 'integer', 'test_mode' => 'boolean', 'paid_at' => 'datetime'];

    public function subscription() { return $this->belongsTo(HotelSubscription::class, 'hotel_subscription_id'); }
}
