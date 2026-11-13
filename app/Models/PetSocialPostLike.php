<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PetSocialPostLike extends Model
{
    protected $guarded = ['id'];
    public function post() { return $this->belongsTo(PetSocialPost::class, 'pet_social_post_id'); }
    public function user() { return $this->belongsTo(User::class); }
}
