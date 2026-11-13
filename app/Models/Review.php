<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Review extends Model
{
    /* Canli tabloda yalniz created_at var (updated_at yok) */
    public $timestamps = false;
    protected $guarded = [];
    protected $casts = ['created_at' => 'datetime'];
    public function user() { return $this->belongsTo(User::class); }
    public function hotel() { return $this->belongsTo(Hotel::class); }
    public function reservation() { return $this->belongsTo(Reservation::class); }
}
