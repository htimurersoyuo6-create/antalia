<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReservationExtensionPaymentAttempt extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'paid_at' => 'datetime'];
    }

    public function extension() { return $this->belongsTo(ReservationExtension::class, 'reservation_extension_id'); }
    public function user() { return $this->belongsTo(User::class); }
}
