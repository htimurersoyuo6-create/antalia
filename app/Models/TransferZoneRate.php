<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class TransferZoneRate extends Model{protected $guarded=[];protected $casts=['fee'=>'decimal:2','is_active'=>'boolean'];public function hotel(){return $this->belongsTo(Hotel::class);}}
