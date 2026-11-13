<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReservationAddonOrder extends Model
{
    protected $guarded = [];
    protected $casts = ['amount' => 'decimal:2', 'decided_at' => 'datetime'];
    public const STATUS = [
        'pending' => 'İşletme yanıtı bekliyor', 'approved' => 'Onaylandı',
        'rejected' => 'Reddedildi', 'completed' => 'Teslim edildi / tamamlandı',
        'cancelled' => 'İptal edildi',
    ];

    public function reservation() { return $this->belongsTo(Reservation::class); }
    public function hotel() { return $this->belongsTo(Hotel::class); }
    public function customer() { return $this->belongsTo(User::class, 'customer_id'); }
    public function items() { return $this->hasMany(ReservationAddonItem::class, 'order_id'); }
    public function financialEntry() { return $this->belongsTo(HotelFinancialEntry::class, 'financial_entry_id'); }
}
