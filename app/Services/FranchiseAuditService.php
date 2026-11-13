<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\FranchiseAudit;
use App\Models\FranchiseGroup;
use App\Models\FranchiseUnit;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class FranchiseAuditService
{
    public function store(FranchiseGroup $group, int $hotelId, array $scores, ?string $notes, User $auditor): FranchiseAudit
    {
        if (! $auditor->isAdmin() && (int) $group->owner_id !== (int) $auditor->id) {
            abort(403);
        }

        $belongs = FranchiseUnit::query()
            ->where('franchise_group_id', $group->id)
            ->where('hotel_id', $hotelId)
            ->exists();

        if (! $belongs) {
            throw ValidationException::withMessages(['hotel_id' => 'Seçilen tesis bu markaya bağlı değil.']);
        }

        $clean = [];
        $total = 0;

        foreach (array_keys(FranchiseRoyaltyService::STANDARDS) as $key) {
            $value = (int) ($scores[$key] ?? 0);

            if ($value < 0 || $value > 10) {
                throw ValidationException::withMessages([$key => 'Puan 0 ile 10 arasında olmalıdır.']);
            }

            $clean[$key] = $value;
            $total += $value;
        }

        $score = (int) round(($total / (count($clean) * 10)) * 100);

        return FranchiseAudit::create([
            'franchise_group_id' => $group->id,
            'hotel_id' => $hotelId,
            'auditor_id' => $auditor->id,
            'audit_date' => now()->toDateString(),
            'score' => $score,
            'checklist' => $clean,
            'status' => 'completed',
            'notes' => $notes,
        ]);
    }
}
