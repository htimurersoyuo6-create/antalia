<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Partner;

use App\Http\Controllers\Controller;
use App\Models\PartnerSettlement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PartnerSettlementApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $partner = $request->attributes->get('partner');

        if (! $partner) {
            return response()->json(['error' => 'unauthorized'], 401);
        }

        $rows = PartnerSettlement::query()
            ->where('partner_hotel_id', $partner->id)
            ->orderByDesc('period')
            ->limit(24)
            ->get()
            ->map(fn (PartnerSettlement $settlement) => [
                'period' => $settlement->period,
                'booking_count' => $settlement->booking_count,
                'gross' => $settlement->gross_cents / 100,
                'commission' => $settlement->commission_cents / 100,
                'net' => $settlement->net_cents / 100,
                'status' => $settlement->status,
                'issued_at' => $settlement->issued_at?->toIso8601String(),
                'paid_at' => $settlement->paid_at?->toIso8601String(),
            ]);

        return response()->json(['data' => $rows]);
    }
}
