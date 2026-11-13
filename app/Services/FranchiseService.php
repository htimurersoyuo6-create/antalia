<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\FranchiseGroup;
use App\Models\FranchiseUnit;
use App\Models\Hotel;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class FranchiseService
{
    public function groupsFor(User $user)
    {
        return FranchiseGroup::query()
            ->when(! $user->isAdmin(), fn ($query) => $query->where('owner_id', $user->id))
            ->withCount('units')
            ->orderBy('name')
            ->get();
    }

    public function resolve(User $user, ?int $groupId = null): FranchiseGroup
    {
        $query = FranchiseGroup::query()->when(! $user->isAdmin(), fn ($q) => $q->where('owner_id', $user->id));

        if ($groupId) {
            return $query->whereKey($groupId)->firstOrFail();
        }

        return $query->orderBy('id')->firstOrFail();
    }

    public function create(User $user, array $data): FranchiseGroup
    {
        $slug = Str::slug($data['name']);

        if ($slug === '') {
            $slug = 'franchise-'.Str::lower(Str::random(6));
        }

        $base = $slug;
        $suffix = 1;

        while (FranchiseGroup::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.(++$suffix);
        }

        return FranchiseGroup::create([
            'owner_id' => $user->id,
            'name' => $data['name'],
            'slug' => $slug,
            'brand_color' => $data['brand_color'] ?? null,
            'royalty_rate' => $data['royalty_rate'] ?? 5.00,
            'marketing_fee_rate' => $data['marketing_fee_rate'] ?? 1.00,
            'terms' => $data['terms'] ?? null,
            'status' => 'active',
        ]);
    }

    public function availableHotels(User $user)
    {
        $attached = FranchiseUnit::query()->pluck('hotel_id');

        return Hotel::query()
            ->when(! $user->isAdmin(), fn ($query) => $query->where('owner_id', $user->id))
            ->whereNotIn('id', $attached)
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    public function attach(FranchiseGroup $group, Hotel $hotel, User $user, ?float $rateOverride = null): FranchiseUnit
    {
        if (! $user->isAdmin() && (int) $group->owner_id !== (int) $user->id) {
            abort(403);
        }

        if (! $user->isAdmin() && (int) $hotel->owner_id !== (int) $user->id) {
            throw ValidationException::withMessages(['hotel_id' => 'Yalnızca size ait tesisleri markaya bağlayabilirsiniz.']);
        }

        if (FranchiseUnit::query()->where('hotel_id', $hotel->id)->exists()) {
            throw ValidationException::withMessages(['hotel_id' => 'Bu tesis başka bir markaya bağlı.']);
        }

        if ($rateOverride !== null && ($rateOverride < 0 || $rateOverride > 50)) {
            throw ValidationException::withMessages(['royalty_rate_override' => 'Royalti oranı 0 ile 50 arasında olmalıdır.']);
        }

        return FranchiseUnit::create([
            'franchise_group_id' => $group->id,
            'hotel_id' => $hotel->id,
            'royalty_rate_override' => $rateOverride,
            'joined_at' => now()->toDateString(),
            'status' => 'active',
        ]);
    }

    public function detach(FranchiseUnit $unit, User $user): void
    {
        if (! $user->isAdmin() && (int) $unit->group->owner_id !== (int) $user->id) {
            abort(403);
        }

        $unit->delete();
    }

    public function summary(FranchiseGroup $group): array
    {
        $period = now()->format('Y-m');

        return [
            'units' => FranchiseUnit::query()->where('franchise_group_id', $group->id)->where('status', 'active')->count(),
            'month_gross' => (float) $group->statements()->where('period', $period)->sum('gross_revenue'),
            'month_due' => (float) $group->statements()->where('period', $period)->sum('total_due'),
            'outstanding' => (float) $group->statements()->where('status', 'issued')->sum('total_due'),
            'paid_total' => (float) $group->statements()->where('status', 'paid')->sum('total_due'),
        ];
    }

    public function recentStatements(FranchiseGroup $group, int $limit = 24)
    {
        return $group->statements()
            ->with('hotel:id,name')
            ->orderByDesc('period')
            ->orderBy('hotel_id')
            ->limit($limit)
            ->get();
    }

    public function recentAudits(FranchiseGroup $group, int $limit = 20)
    {
        return $group->audits()
            ->with(['hotel:id,name', 'auditor:id,name'])
            ->orderByDesc('audit_date')
            ->limit($limit)
            ->get();
    }

    public function units(FranchiseGroup $group)
    {
        return $group->units()
            ->with('hotel:id,name,slug,status')
            ->orderBy('id')
            ->get();
    }

    public function hotelOptions(FranchiseGroup $group)
    {
        return $group->units()->with('hotel:id,name')->get()->map(fn (FranchiseUnit $unit) => [
            'id' => $unit->hotel_id,
            'name' => $unit->hotel?->name,
        ])->filter(fn (array $row) => $row['name'] !== null)->values();
    }
}
