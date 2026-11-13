<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class City extends Model
{
    public $timestamps = false;
    protected $guarded = [];
    public function districts() { return $this->hasMany(District::class); }
    public function hotels() { return $this->hasMany(Hotel::class); }
}
