<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PetVaccination extends Model
{
    protected $guarded = [];

    protected $casts = [
        'administered_at' => 'date',
        'vaccine_type' => 'string',
        'next_due_at' => 'date',
        'reminder_enabled' => 'boolean',
    ];

    public function pet() { return $this->belongsTo(Pet::class); }
    public function owner() { return $this->belongsTo(User::class, 'user_id'); }

    public function getReminderStateAttribute(): string
    {
        if (!$this->next_due_at || !$this->reminder_enabled) return 'none';
        if ($this->next_due_at->isBefore(today())) return 'overdue';
        if ($this->next_due_at->lte(today()->addDays(30))) return 'upcoming';
        return 'scheduled';
    }
}
