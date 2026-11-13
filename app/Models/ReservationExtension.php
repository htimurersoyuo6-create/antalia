<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReservationExtension extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'current_check_out' => 'date',
            'requested_check_out' => 'date',
            'decided_at' => 'datetime',
            'quoted_room_total' => 'decimal:2',
            'quoted_discount_total' => 'decimal:2',
            'quoted_extras_total' => 'decimal:2',
            'quoted_total' => 'decimal:2',
        ];
    }

    public const STATUS = [
        'pending' => 'Yanıt bekliyor',
        'approved' => 'Onaylandı',
        'rejected' => 'Reddedildi',
    ];

    public function reservation()
    {
        return $this->belongsTo(Reservation::class);
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function decider()
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function financialEntry()
    {
        return $this->belongsTo(HotelFinancialEntry::class, 'hotel_financial_entry_id');
    }
}
