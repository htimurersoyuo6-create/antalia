<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GeofencedAlert extends Model
{
    protected $guarded = [];

    protected $casts = [
        'lat' => 'decimal:7',
        'lng' => 'decimal:7',
        'radius_m' => 'integer',
        'is_public' => 'boolean',
        'reported_at' => 'datetime',
        'notified_user_ids' => 'array',
    ];

    public function pet()
    {
        return $this->belongsTo(Pet::class);
    }

    public function hotel()
    {
        return $this->belongsTo(Hotel::class);
    }

    public function scopeOpen($query)
    {
        return $query->where('status', 'open');
    }
}
