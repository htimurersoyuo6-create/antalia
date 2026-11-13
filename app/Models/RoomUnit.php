<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class RoomUnit extends Model
{
    protected $table = 'hotel_room_units';
    protected $guarded=[];
    public const STATUS=[
        'available'=>'Müsait','cleaning'=>'Temizlikte',
        'maintenance'=>'Bakımda','renovation'=>'Tadilatta',
    ];
    public function room(){return $this->belongsTo(RoomType::class,'room_type_id');}
    public function reservations(){return $this->hasMany(Reservation::class,'room_unit_id');}
    public function getStatusLabelAttribute(): string { return self::STATUS[$this->status] ?? $this->status; }
}
