<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Reservation extends Model
{
    protected $guarded = [];
    protected static function booted(): void
    {
        static::updated(function (self $reservation) {
            if (!$reservation->wasChanged('status') || !in_array($reservation->status,['cancelled','rejected'],true)) return;
            $reservation->reservationTransfer?->update(['status'=>'cancelled']);
            if ((int)$reservation->points_redeemed < 1 || $reservation->payment_status === 'paid') return;
            \Illuminate\Support\Facades\DB::transaction(function () use ($reservation) {
                $already=PointTransaction::where('reservation_id',$reservation->id)->where('type','earned')->where('description','İptal edilen rezervasyondan PotiPuan iadesi')->exists();
                if ($already) return;
                $user=User::whereKey($reservation->user_id)->lockForUpdate()->first();
                if (!$user) return;
                PointTransaction::create(['user_id'=>$user->id,'reservation_id'=>$reservation->id,'points'=>(int)$reservation->points_redeemed,'type'=>'earned','description'=>'İptal edilen rezervasyondan PotiPuan iadesi']);
                $user->increment('reward_points',(int)$reservation->points_redeemed);
            });
        });
    }

    protected $casts = [
        'check_in' => 'date', 'check_out' => 'date', 'early_departure_date' => 'date',
        'early_departure_requested_at' => 'datetime', 'early_departure_decided_at' => 'datetime',
        'owner_reminded_at' => 'datetime',
        'transfer_location_shared_at' => 'datetime', 'transfer_started_at' => 'datetime', 'transfer_completed_at' => 'datetime',
        'total_price' => 'decimal:2', 'extras_total' => 'decimal:2', 'discount_total' => 'decimal:2',
        'vip_discount_total' => 'decimal:2', 'vip_platform_discount_total' => 'decimal:2',
        'grooming_fee'=>'decimal:2','insurance_fee'=>'decimal:2','insurance_provider_amount'=>'decimal:2','points_discount'=>'decimal:2',
    ];
    public const STATUS = [
        'pending' => 'Onay bekliyor', 'confirmed' => 'Onaylandı', 'rejected' => 'Reddedildi',
        'cancelled' => 'İptal edildi', 'completed' => 'Tamamlandı',
    ];
    public const BLOCKING = ['pending', 'confirmed'];
    public function user() { return $this->belongsTo(User::class); }
    public function hotel() { return $this->belongsTo(Hotel::class); }
    public function room() { return $this->belongsTo(RoomType::class, 'room_type_id'); }
    public function roomUnit() { return $this->belongsTo(RoomUnit::class, 'room_unit_id'); }
    public function pet() { return $this->belongsTo(Pet::class); }
    public function extras() { return $this->hasMany(ReservationExtra::class); }
    public function addonOrders() { return $this->hasMany(ReservationAddonOrder::class)->orderByDesc('id'); }
    public function messages() { return $this->hasMany(ReservationMessage::class)->orderBy('id'); }
    public function records() { return $this->hasMany(ReservationRecord::class); }
    public function updates() { return $this->hasMany(ReservationUpdate::class)->orderBy('id'); }
    public function guestReview() { return $this->hasOne(HotelGuestReview::class); }
    public function extensions() { return $this->hasMany(ReservationExtension::class)->orderBy('id'); }
    public function invoice() { return $this->hasOne(ReservationInvoice::class); }
    public function financialEntries() { return $this->hasMany(HotelFinancialEntry::class); }
    public function paymentTransaction() { return $this->hasOne(PaymentTransaction::class); }
    public function petTransfer() { return $this->hasOne(PetTransfer::class); }
    public function reservationTransfer() { return $this->hasOne(ReservationTransfer::class); }
    public function groomingOption() { return $this->belongsTo(GroomingOption::class); }
    public function pointTransactions() { return $this->hasMany(PointTransaction::class); }
    public function cameraAccess() { return $this->hasOne(ReservationCameraAccess::class); }
    public function calls() { return $this->hasMany(ReservationCall::class)->orderByDesc('id'); }
    public function review() { return $this->hasOne(Review::class); }
    public function waivers() { return $this->hasMany(Waiver::class); }
    public function dailyReports() { return $this->hasMany(DailyReport::class); }
    public function belongings() { return $this->hasOne(ReservationBelonging::class); }
    public function preauthBlocks() { return $this->hasMany(PreauthBlock::class); }
    public function vaccinationCards() { return $this->hasMany(VaccinationCard::class); }
    public function tasks() { return $this->hasMany(PetTask::class); }
    public function getStatusLabelAttribute(): string { return self::STATUS[$this->status] ?? $this->status; }
    public function canBeCancelled(): bool
    {
        return in_array($this->status, ['pending', 'confirmed'], true) && $this->check_in->isFuture();
    }
    public function canBeReviewed(): bool { return $this->status === 'completed' && !$this->review; }
    public static function makeCode(): string
    {
        do { $c = 'PO'.strtoupper(\Illuminate\Support\Str::random(8)); } while (self::where('code', $c)->exists());
        return $c;
    }
}
