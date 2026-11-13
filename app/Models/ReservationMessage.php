<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReservationMessage extends Model
{
    public $timestamps = false;
    protected $guarded = [];
    protected $casts = ['created_at' => 'datetime'];

    protected static function booted(): void
    {
        static::creating(function (self $message): void {
            $message->created_at ??= now();
        });
    }
    public function user() { return $this->belongsTo(User::class); }
    public function reservation() { return $this->belongsTo(Reservation::class); }
}
