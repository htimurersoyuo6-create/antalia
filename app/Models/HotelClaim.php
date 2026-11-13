<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HotelClaim extends Model
{
    protected $guarded = [];
    protected $casts = ['decided_at' => 'datetime'];
    public const STATUS = ['pending' => 'İncelemede', 'approved' => 'Onaylandı', 'rejected' => 'Reddedildi'];
    public function hotel() { return $this->belongsTo(Hotel::class); }
    public function user() { return $this->belongsTo(User::class); }
    public function decidedBy() { return $this->belongsTo(User::class, 'decided_by'); }
}
