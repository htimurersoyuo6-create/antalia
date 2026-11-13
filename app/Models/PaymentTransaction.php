<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentTransaction extends Model
{
    protected $table = 'transactions';

    protected $fillable = [
        'reservation_id', 'reservation_payment_attempt_id', 'hotel_id', 'user_id',
        'gateway', 'gateway_payment_id', 'currency', 'gross_amount',
        'vip_discount_total', 'hotel_discount_total', 'platform_discount_amount',
        'commission_percentage', 'commission_amount', 'hotel_payout', 'insurance_provider_amount', 'status',
        'gateway_metadata', 'paid_at', 'payout_status', 'payout_at', 'payout_reference',
    ];

    protected $casts = [
        'gross_amount' => 'decimal:2',
        'vip_discount_total' => 'decimal:2', 'hotel_discount_total' => 'decimal:2', 'platform_discount_amount' => 'decimal:2',
        'commission_percentage' => 'decimal:2',
        'commission_amount' => 'decimal:2',
        'hotel_payout' => 'decimal:2',
        'insurance_provider_amount'=>'decimal:2',
        'gateway_metadata' => 'array',
        'paid_at' => 'datetime',
        'payout_at' => 'datetime',
    ];

    public function reservation() { return $this->belongsTo(Reservation::class); }
    public function paymentAttempt() { return $this->belongsTo(ReservationPaymentAttempt::class, 'reservation_payment_attempt_id'); }
    public function hotel() { return $this->belongsTo(Hotel::class); }
    public function user() { return $this->belongsTo(User::class); }
}
