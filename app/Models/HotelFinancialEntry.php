<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HotelFinancialEntry extends Model
{
    protected $guarded = [];
    protected $casts = [
        'amount' => 'decimal:2',
        'due_date' => 'date',
        'paid_at' => 'datetime',
    ];

    public function hotel() { return $this->belongsTo(Hotel::class); }
    public function reservation() { return $this->belongsTo(Reservation::class); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
    public function addonOrder() { return $this->hasOne(ReservationAddonOrder::class, 'financial_entry_id'); }
}
