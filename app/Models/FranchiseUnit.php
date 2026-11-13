<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FranchiseUnit extends Model
{
    protected $guarded = [];

    protected $casts = [
        'royalty_rate_override' => 'decimal:2',
        'joined_at' => 'date',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(FranchiseGroup::class, 'franchise_group_id');
    }

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    public function effectiveRate(): float
    {
        return (float) ($this->royalty_rate_override ?? $this->group?->royalty_rate ?? 0);
    }
}
