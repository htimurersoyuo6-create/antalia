<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HotelChat extends Model
{
    protected $guarded = [];
    protected $casts = ['archived_at' => 'datetime', 'last_message_at' => 'datetime'];
    public function hotel() { return $this->belongsTo(Hotel::class); }
    public function user() { return $this->belongsTo(User::class); }
    public function messages() { return $this->hasMany(HotelChatMessage::class)->orderBy('id'); }
    public function latestMessage() { return $this->hasOne(HotelChatMessage::class)->latestOfMany(); }
}
