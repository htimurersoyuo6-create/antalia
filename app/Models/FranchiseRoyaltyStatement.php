<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FranchiseRoyaltyStatement extends Model
{
    protected $guarded = [];

    protected $casts = [
        'gross_revenue' => 'decimal:2',
        'royalty_rate' => 'decimal:2',
        'royalty_amount' => 'decimal:2',
        'marketing_fee' => 'decimal:2',
        'total_due' => 'decimal:2',
        'issued_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(FranchiseGroup::class, 'franchise_group_id');
    }

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }
}
