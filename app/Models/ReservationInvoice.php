<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReservationInvoice extends Model
{
    protected $guarded = [];
    protected $casts = ['issued_at' => 'date', 'email_sent_at' => 'datetime', 'whatsapp_sent_at' => 'datetime', 'e_invoice_sent_at' => 'datetime', 'amount' => 'decimal:2'];

    public function reservation() { return $this->belongsTo(Reservation::class); }
    public function hotel() { return $this->belongsTo(Hotel::class); }
    public function uploader() { return $this->belongsTo(User::class, 'uploaded_by'); }

    public function isIssued(): bool
    {
        return $this->status === 'issued' && $this->invoice_number && $this->document_path;
    }

    public static function ensurePending(Reservation $reservation): ?self
    {
        if (!in_array($reservation->status, ['confirmed', 'completed'], true) || $reservation->payment_status !== 'paid') return null;
        return static::firstOrCreate(
            ['reservation_id' => $reservation->id],
            ['hotel_id' => $reservation->hotel_id, 'status' => 'pending', 'amount' => $reservation->total_price, 'currency' => 'TRY', 'email_status' => 'pending', 'whatsapp_status' => 'not_configured']
        );
    }
}
