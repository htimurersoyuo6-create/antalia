<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FranchiseAudit extends Model
{
    protected $guarded = [];

    protected $casts = [
        'audit_date' => 'date',
        'checklist' => 'array',
        'score' => 'integer',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(FranchiseGroup::class, 'franchise_group_id');
    }

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    public function auditor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'auditor_id');
    }
}
