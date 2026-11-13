<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FranchiseGroup extends Model
{
    protected $guarded = [];

    protected $casts = [
        'royalty_rate' => 'decimal:2',
        'marketing_fee_rate' => 'decimal:2',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function units(): HasMany
    {
        return $this->hasMany(FranchiseUnit::class);
    }

    public function statements(): HasMany
    {
        return $this->hasMany(FranchiseRoyaltyStatement::class);
    }

    public function audits(): HasMany
    {
        return $this->hasMany(FranchiseAudit::class);
    }
}
