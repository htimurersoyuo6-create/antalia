<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HotelChatMessage extends Model
{
    protected $guarded = [];
    public function chat() { return $this->belongsTo(HotelChat::class, 'hotel_chat_id'); }
    public function sender() { return $this->belongsTo(User::class, 'sender_user_id'); }
}
