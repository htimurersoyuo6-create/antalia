<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReservationPaymentAttempt extends Model
{
    protected $guarded = [];
    protected $casts = ['amount' => 'decimal:2', 'paid_at' => 'datetime'];

    public function reservation() { return $this->belongsTo(Reservation::class); }
    public function user() { return $this->belongsTo(User::class); }
}
