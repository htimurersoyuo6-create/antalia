<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PartnerHotel;
use App\Models\PartnerSettlement;
use App\Services\PartnerSettlementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Validation\ValidationException;

class PartnerSettlementController extends Controller
{
    public function index(Request $request, PartnerSettlementService $settlements): View
    {
        $period = (string) ($request->query('period') ?: now()->subMonthNoOverflow()->format('Y-m'));
        $partners = PartnerHotel::query()->orderBy('name')->get();

        $rows = $partners->map(function (PartnerHotel $partner) use ($period): array {
            $settlement = PartnerSettlement::query()
                ->where('partner_hotel_id', $partner->id)
                ->where('period', $period)
                ->first();

            return [
                'partner' => $partner,
                'settlement' => $settlement,
            ];
        });

        return view('admin.partner-settlements', [
            'period' => $period,
            'rows' => $rows,
            'totals' => $settlements->periodTotals($period),
        ]);
    }

    public function generate(Request $request, PartnerSettlementService $settlements): RedirectResponse
    {
        $data = $request->validate([
            'period' => ['required', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
            'partner_id' => ['nullable', 'integer', 'exists:partner_hotels,id'],
            'issue' => ['nullable', 'boolean'],
        ]);

        try {
            if (! empty($data['partner_id'])) {
                $partner = PartnerHotel::query()->findOrFail((int) $data['partner_id']);
                $settlements->generate($partner, $data['period'], (bool) ($data['issue'] ?? true));
            } else {
                $settlements->generateAll($data['period'], (bool) ($data['issue'] ?? true));
            }
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return back()->with('ok', $data['period'].' dönemi partner mutabakatı oluşturuldu.');
    }

    public function pay(Request $request, PartnerSettlement $settlement, PartnerSettlementService $settlements): RedirectResponse
    {
        try {
            $settlements->markPaid($settlement);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return back()->with('ok', 'Partner ödemesi işaretlendi.');
    }
}
