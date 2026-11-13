<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PetMedicalDocument extends Model
{
    protected $guarded = [];

    protected $casts = ['share_with_active_hotel' => 'boolean'];

    public function pet() { return $this->belongsTo(Pet::class); }
    public function owner() { return $this->belongsTo(User::class, 'user_id'); }
}
