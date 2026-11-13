<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\FranchiseGroup;
use App\Models\FranchiseRoyaltyStatement;
use App\Models\FranchiseUnit;
use App\Models\Reservation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class FranchiseRoyaltyService
{
    public const STANDARDS = [
        'hygiene' => 'Hijyen ve temizlik',
        'safety' => 'Güvenlik ve acil durum',
        'brand' => 'Marka standartları ve görünüm',
        'staff' => 'Personel eğitimi ve tutum',
        'documentation' => 'Evrak, ruhsat ve kayıt düzeni',
    ];

    public function generate(FranchiseGroup $group, string $period, bool $issue = false): array
    {
        if (! preg_match('~^\d{4}-(0[1-9]|1[0-2])$~', $period)) {
            throw ValidationException::withMessages(['period' => 'Dönem YYYY-MM biçiminde olmalıdır.']);
        }

        $start = $period.'-01';
        $end = date('Y-m-t', strtotime($start));

        if (strtotime($start) > strtotime(now()->format('Y-m-01'))) {
            throw ValidationException::withMessages(['period' => 'Gelecek dönem için royalti hesaplanamaz.']);
        }

        $units = FranchiseUnit::query()
            ->with('hotel')
            ->where('franchise_group_id', $group->id)
            ->where('status', 'active')
            ->get();

        $created = 0;
        $updated = 0;

        DB::transaction(function () use ($units, $group, $period, $start, $end, $issue, &$created, &$updated): void {
            foreach ($units as $unit) {
                $gross = (float) Reservation::query()
                    ->where('hotel_id', $unit->hotel_id)
                    ->whereIn('status', ['confirmed', 'completed'])
                    ->where('payment_status', 'paid')
                    ->whereBetween('check_in', [$start, $end])
                    ->sum('total_price');

                $rate = $unit->effectiveRate();
                $royalty = round($gross * $rate / 100, 2);
                $marketing = round($gross * (float) $group->marketing_fee_rate / 100, 2);
                $total = round($royalty + $marketing, 2);

                $statement = FranchiseRoyaltyStatement::query()->firstOrNew([
                    'franchise_group_id' => $group->id,
                    'hotel_id' => $unit->hotel_id,
                    'period' => $period,
                ]);

                $isNew = ! $statement->exists;

                if (! $isNew && $statement->status === 'paid') {
                    continue;
                }

                $statement->fill([
                    'gross_revenue' => $gross,
                    'royalty_rate' => $rate,
                    'royalty_amount' => $royalty,
                    'marketing_fee' => $marketing,
                    'total_due' => $total,
                    'status' => $issue ? 'issued' : ($statement->status ?: 'draft'),
                    'issued_at' => $issue ? ($statement->issued_at ?? now()) : $statement->issued_at,
                ]);

                $statement->save();

                $isNew ? $created++ : $updated++;
            }
        });

        Log::info('franchise.royalty_generated', [
            'group_id' => $group->id,
            'period' => $period,
            'created' => $created,
            'updated' => $updated,
            'issued' => $issue,
        ]);

        return ['created' => $created, 'updated' => $updated, 'period' => $period];
    }

    public function markPaid(FranchiseRoyaltyStatement $statement): FranchiseRoyaltyStatement
    {
        if ($statement->status === 'draft') {
            throw ValidationException::withMessages(['statement' => 'Önce royalti bildirimi kesilmelidir.']);
        }

        $statement->forceFill([
            'status' => 'paid',
            'paid_at' => now(),
        ])->save();

        return $statement->refresh();
    }

    public function runAll(string $period, bool $issue): array
    {
        $groups = FranchiseGroup::query()->where('status', 'active')->get();
        $results = [];

        foreach ($groups as $group) {
            $results[$group->id] = $this->generate($group, $period, $issue);
        }

        return $results;
    }
}
