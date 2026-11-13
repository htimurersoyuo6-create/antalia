<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\PartnerBooking;
use App\Models\PartnerHotel;
use App\Models\PartnerSettlement;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class PartnerSettlementService
{
    public function generate(PartnerHotel $partner, string $period, bool $issue = false): PartnerSettlement
    {
        if (! preg_match('~^\d{4}-(0[1-9]|1[0-2])$~', $period)) {
            throw ValidationException::withMessages(['period' => 'Dönem YYYY-MM biçiminde olmalıdır.']);
        }

        $start = $period.'-01 00:00:00';
        $end = date('Y-m-t', strtotime($period.'-01')).' 23:59:59';

        $bookings = PartnerBooking::query()
            ->where('partner_hotel_id', $partner->id)
            ->where('status', '!=', 'cancelled')
            ->whereBetween('created_at', [$start, $end]);

        $count = (clone $bookings)->count();
        $gross = (int) (clone $bookings)->sum('total_cents');
        $commission = (int) (clone $bookings)->sum('commission_cents');

        $settlement = PartnerSettlement::query()->firstOrNew([
            'partner_hotel_id' => $partner->id,
            'period' => $period,
        ]);

        if ($settlement->exists && $settlement->status === 'paid') {
            throw ValidationException::withMessages(['period' => 'Bu dönem ödenmiş; mutabakat değiştirilemez.']);
        }

        $settlement->fill([
            'booking_count' => $count,
            'gross_cents' => $gross,
            'commission_cents' => $commission,
            'net_cents' => max(0, $gross - $commission),
            'status' => $issue ? 'issued' : ($settlement->status ?: 'draft'),
            'issued_at' => $issue ? ($settlement->issued_at ?? now()) : $settlement->issued_at,
        ])->save();

        Log::info('partner.settlement_generated', [
            'partner_hotel_id' => $partner->id,
            'period' => $period,
            'booking_count' => $count,
            'commission_cents' => $commission,
            'issued' => $issue,
        ]);

        return $settlement->refresh();
    }

    public function generateAll(string $period, bool $issue = false): array
    {
        $results = [];

        PartnerHotel::query()->where('is_active', true)->orderBy('id')->each(function (PartnerHotel $partner) use ($period, $issue, &$results): void {
            $results[] = $this->generate($partner, $period, $issue);
        });

        return $results;
    }

    public function markPaid(PartnerSettlement $settlement): PartnerSettlement
    {
        if ($settlement->status === 'draft') {
            throw ValidationException::withMessages(['settlement' => 'Önce mutabakat bildirimi kesilmelidir.']);
        }

        if ($settlement->status === 'paid') {
            return $settlement;
        }

        DB::transaction(function () use ($settlement): void {
            $settlement->forceFill([
                'status' => 'paid',
                'paid_at' => now(),
            ])->save();
        });

        return $settlement->refresh();
    }

    public function periodTotals(string $period): array
    {
        $rows = PartnerSettlement::query()->where('period', $period)->get();

        return [
            'count' => $rows->count(),
            'gross_cents' => (int) $rows->sum('gross_cents'),
            'commission_cents' => (int) $rows->sum('commission_cents'),
            'net_cents' => (int) $rows->sum('net_cents'),
            'outstanding_cents' => (int) $rows->where('status', 'issued')->sum('net_cents'),
        ];
    }
}
