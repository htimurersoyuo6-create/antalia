<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PetSocialPost extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['public_consent_at' => 'datetime', 'moderated_at' => 'datetime'];

    public function user() { return $this->belongsTo(User::class); }
    public function pet() { return $this->belongsTo(Pet::class); }
    public function moderator() { return $this->belongsTo(User::class, 'moderated_by'); }
    public function likes() { return $this->hasMany(PetSocialPostLike::class); }
    public function comments() { return $this->hasMany(PetSocialComment::class); }
    public function scopePubliclyVisible($query) { return $query->where('status', 'approved')->whereNotNull('public_consent_at'); }
}
