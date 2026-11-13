<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HotelSubscription extends Model
{
    protected $guarded = [];
    protected $casts = ['amount' => 'decimal:2', 'paid_at' => 'datetime', 'starts_at' => 'datetime', 'ends_at' => 'datetime'];

    public function hotel() { return $this->belongsTo(Hotel::class); }
    public function plan() { return $this->belongsTo(SubscriptionPlan::class, 'plan_id'); }
    public function requester() { return $this->belongsTo(User::class, 'requested_by'); }
    public function approver() { return $this->belongsTo(User::class, 'approved_by'); }
    public function payments() { return $this->hasMany(HotelSubscriptionPayment::class); }

    public function scopeCurrent($query)
    {
        return $query->where('status', 'active')->where(function ($q) {
            $q->where('payment_status', 'paid')->orWhere('payment_method', 'trial');
        })->where('starts_at', '<=', now())->where('ends_at', '>', now());
    }
}
