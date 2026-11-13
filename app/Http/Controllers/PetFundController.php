<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Pet;
use App\Services\PetFundService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

class PetFundController extends Controller
{
    public function show(Request $request, Pet $pet, PetFundService $fund): View
    {
        $this->authorizePet($request, $pet);

        $summary = $fund->summary($pet);

        return view('pets.fund', [
            'pet' => $pet,
            'balanceCents' => $summary['balance_cents'],
            'entries' => $summary['entries'],
        ]);
    }

    public function store(Request $request, Pet $pet, PetFundService $fund): RedirectResponse
    {
        $this->authorizePet($request, $pet);

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:1', 'max:100000'],
            'note' => ['nullable', 'string', 'max:200'],
            'status' => ['nullable', 'in:pending,confirmed'],
        ]);

        try {
            $fund->donate(
                $pet,
                $request->user(),
                (int) round(((float) $data['amount']) * 100),
                $data['note'] ?? null,
                $data['status'] ?? 'confirmed'
            );
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['amount' => $e->getMessage()]);
        }

        return back()->with('ok', 'Fon kaydı oluşturuldu.');
    }

    private function authorizePet(Request $request, Pet $pet): void
    {
        $user = $request->user();

        abort_unless($user->isAdmin() || (int) $pet->user_id === (int) $user->id, 403);
    }
}
