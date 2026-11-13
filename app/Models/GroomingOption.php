<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class GroomingOption extends Model {
    protected $guarded=[]; protected $casts=['price'=>'decimal:2','is_active'=>'boolean'];
    public function hotel(){return $this->belongsTo(Hotel::class);}
}
