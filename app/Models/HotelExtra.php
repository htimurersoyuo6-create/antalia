<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HotelExtra extends Model
{
    protected $guarded = [];
    protected $casts = ['price' => 'decimal:2', 'active' => 'boolean', 'available_after_booking' => 'boolean'];
    public const CATEGORIES = ['service' => 'Hizmet', 'product' => 'Ürün', 'food' => 'Mama ve yiyecek', 'health' => 'Sağlık ve aşı'];
    public const UNITS = ['item' => 'adet başına', 'stay' => 'hizmet başına', 'night' => 'gece başına'];
    public function hotel() { return $this->belongsTo(Hotel::class); }
    public function addonItems() { return $this->hasMany(ReservationAddonItem::class); }
    public function getUnitLabelAttribute(): string { return self::UNITS[$this->unit] ?? $this->unit; }
}
