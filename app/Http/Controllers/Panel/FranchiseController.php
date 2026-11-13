<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\FranchiseGroup;
use App\Models\FranchiseRoyaltyStatement;
use App\Models\FranchiseUnit;
use App\Models\Hotel;
use App\Services\FranchiseAuditService;
use App\Services\FranchiseRoyaltyService;
use App\Services\FranchiseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Validation\ValidationException;

class FranchiseController extends Controller
{
    public function index(Request $request, FranchiseService $franchises): View
    {
        $user = $request->user();
        $groups = $franchises->groupsFor($user);
        $group = $groups->firstWhere('id', (int) $request->query('group')) ?? $groups->first();

        $data = [
            'groups' => $groups,
            'group' => $group,
            'summary' => null,
            'units' => collect(),
            'availableHotels' => collect(),
            'statements' => collect(),
            'audits' => collect(),
            'standards' => FranchiseRoyaltyService::STANDARDS,
            'hotelOptions' => collect(),
            'period' => now()->format('Y-m'),
        ];

        if ($group) {
            $data['summary'] = $franchises->summary($group);
            $data['units'] = $franchises->units($group);
            $data['availableHotels'] = $franchises->availableHotels($user);
            $data['statements'] = $franchises->recentStatements($group);
            $data['audits'] = $franchises->recentAudits($group);
            $data['hotelOptions'] = $franchises->hotelOptions($group);
        }

        return view('panel.franchise', $data);
    }

    public function store(Request $request, FranchiseService $franchises): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'brand_color' => ['nullable', 'string', 'max:16'],
            'royalty_rate' => ['nullable', 'numeric', 'min:0', 'max:50'],
            'marketing_fee_rate' => ['nullable', 'numeric', 'min:0', 'max:50'],
            'terms' => ['nullable', 'string', 'max:5000'],
        ]);

        $group = $franchises->create($request->user(), $data);

        return redirect()
            ->route('panel.franchise.index', ['group' => $group->id])
            ->with('ok', 'Marka oluşturuldu.');
    }

    public function attach(Request $request, FranchiseGroup $group, FranchiseService $franchises): RedirectResponse
    {
        $this->authorizeGroup($request, $group);

        $data = $request->validate([
            'hotel_id' => ['required', 'integer', 'exists:hotels,id'],
            'royalty_rate_override' => ['nullable', 'numeric', 'min:0', 'max:50'],
        ]);

        $hotel = Hotel::query()->findOrFail((int) $data['hotel_id']);

        try {
            $franchises->attach(
                $group,
                $hotel,
                $request->user(),
                isset($data['royalty_rate_override']) ? (float) $data['royalty_rate_override'] : null
            );
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return back()->with('ok', $hotel->name.' markaya bağlandı.');
    }

    public function detach(Request $request, FranchiseGroup $group, FranchiseUnit $unit, FranchiseService $franchises): RedirectResponse
    {
        $this->authorizeGroup($request, $group);

        abort_unless((int) $unit->franchise_group_id === (int) $group->id, 404);

        $franchises->detach($unit, $request->user());

        return back()->with('ok', 'Tesis markadan ayrıldı; geçmiş royalti kayıtları korunuyor.');
    }

    public function generate(Request $request, FranchiseGroup $group, FranchiseRoyaltyService $royalties): RedirectResponse
    {
        $this->authorizeGroup($request, $group);

        $data = $request->validate([
            'period' => ['required', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
            'issue' => ['nullable', 'boolean'],
        ]);

        try {
            $result = $royalties->generate($group, $data['period'], (bool) ($data['issue'] ?? false));
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return back()->with('ok', $result['period'].' dönemi: '.$result['created'].' yeni, '.$result['updated'].' güncellenen royalti bildirimi.');
    }

    public function pay(Request $request, FranchiseGroup $group, FranchiseRoyaltyStatement $statement, FranchiseRoyaltyService $royalties): RedirectResponse
    {
        $this->authorizeGroup($request, $group);

        abort_unless((int) $statement->franchise_group_id === (int) $group->id, 404);

        try {
            $royalties->markPaid($statement);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return back()->with('ok', 'Royalti ödemesi işaretlendi.');
    }

    public function audit(Request $request, FranchiseGroup $group, FranchiseAuditService $audits): RedirectResponse
    {
        $this->authorizeGroup($request, $group);

        $data = $request->validate([
            'hotel_id' => ['required', 'integer', 'exists:hotels,id'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'scores' => ['required', 'array'],
        ]);

        try {
            $audit = $audits->store($group, (int) $data['hotel_id'], (array) $data['scores'], $data['notes'] ?? null, $request->user());
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return back()->with('ok', 'Denetim kaydedildi. Marka uyum puanı: '.$audit->score.'/100');
    }

    private function authorizeGroup(Request $request, FranchiseGroup $group): void
    {
        $user = $request->user();

        abort_unless($user->isAdmin() || (int) $group->owner_id === (int) $user->id, 403);
    }
}
