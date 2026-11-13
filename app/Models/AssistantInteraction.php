<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssistantInteraction extends Model
{
    protected $guarded = [];

    protected $casts = [
        'confidence' => 'decimal:2',
        'handoff' => 'boolean',
    ];

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function faq(): BelongsTo
    {
        return $this->belongsTo(AssistantFaq::class, 'matched_faq_id');
    }
}
