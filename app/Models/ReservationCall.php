<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class ReservationCall extends Model
{
    protected $guarded = [];
    protected $casts = ['expires_at' => 'datetime', 'answered_at' => 'datetime', 'ended_at' => 'datetime'];

    protected static function booted(): void
    {
        static::created(function (self $call): void {
            DB::afterCommit(function () use ($call): void {
                PushNotificationService::sendIncomingCall($call);
            });
        });
    }

    public function reservation() { return $this->belongsTo(Reservation::class); }
    public function caller() { return $this->belongsTo(User::class, 'caller_id'); }
    public function recipient() { return $this->belongsTo(User::class, 'recipient_id'); }
    public function signals() { return $this->hasMany(ReservationCallSignal::class, 'call_id'); }
    public function recording() { return $this->hasOne(ReservationCallRecording::class, 'call_id'); }
}
