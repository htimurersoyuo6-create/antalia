<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Waiver extends Model
{
    protected $guarded = [];
    protected $casts = ['signature_data' => 'encrypted', 'emergency_budget' => 'decimal:2', 'signed_at' => 'datetime'];
    public function reservation() { return $this->belongsTo(Reservation::class); }
    public function user() { return $this->belongsTo(User::class); }
    public function pet() { return $this->belongsTo(Pet::class); }
}