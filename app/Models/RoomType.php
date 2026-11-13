<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class RoomType extends Model
{
    public $timestamps = false;
    protected $guarded = [];
    public const PET_TYPES = ['dog' => 'Köpek', 'cat' => 'Kedi', 'other' => 'Diğer'];
    public const SIZES = ['any' => 'Her boy', 'small' => 'Küçük', 'medium' => 'Orta', 'large' => 'Büyük'];
    public function hotel() { return $this->belongsTo(Hotel::class); }
    public function priceRules() { return $this->hasMany(PriceRule::class); }
    public function reservations() { return $this->hasMany(Reservation::class); }
    public function inventoryUnits() { return $this->hasMany(RoomUnit::class, 'room_type_id'); }
}
