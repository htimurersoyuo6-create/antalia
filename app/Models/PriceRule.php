<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class PriceRule extends Model
{
    public $timestamps = false;
    protected $guarded = [];
    protected $casts = ['date_from' => 'date', 'date_to' => 'date'];
    public function room() { return $this->belongsTo(RoomType::class, 'room_type_id'); }
}
