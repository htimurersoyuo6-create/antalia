<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubscriptionPlan extends Model
{
    protected $guarded = [];
    protected $casts = ['active' => 'boolean', 'features' => 'array'];

    public function subscriptions() { return $this->hasMany(HotelSubscription::class, 'plan_id'); }
}
