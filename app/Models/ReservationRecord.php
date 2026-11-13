<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReservationRecord extends Model
{
    protected $guarded = [];
    protected $casts = ['hotel_signed_at' => 'datetime', 'customer_signed_at' => 'datetime'];
    public const TYPES = ['checkin' => 'Giriş tutanağı', 'checkout' => 'Çıkış tutanağı'];
    public function reservation() { return $this->belongsTo(Reservation::class); }
    public function getTypeLabelAttribute(): string { return self::TYPES[$this->type] ?? $this->type; }
    public function isComplete(): bool { return $this->hotel_signed_at && $this->customer_signed_at; }
}
