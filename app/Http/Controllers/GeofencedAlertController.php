<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\GeofencedAlert;
use App\Models\Pet;
use App\Services\GeofencedAlertService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class GeofencedAlertController extends Controller
{
    public function store(Request $request, Pet $pet, GeofencedAlertService $service): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->isAdmin() || (int) $pet->user_id === (int) $user->id, 403);

        $data = $request->validate([
            'lat' => 'required|numeric|between:-90,90',
            'lng' => 'required|numeric|between:-180,180',
            'radius_m' => 'nullable|integer|min:500|max:50000',
            'note' => 'nullable|string|max:500',
        ]);

        $alert = $service->report($pet, (float) $data['lat'], (float) $data['lng'], (int) ($data['radius_m'] ?? 5000), $data['note'] ?? null);

        return back()->with('ok', 'Kayıp pet uyarısı yayınlandı. '.count($alert->notified_user_ids ?? []).' kişiye bildirim gönderildi.');
    }

    public function resolve(Request $request, GeofencedAlert $alert, GeofencedAlertService $service): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->isAdmin() || (int) $alert->pet?->user_id === (int) $user->id, 403);
        $service->resolve($alert, (int) $user->id);

        return back()->with('ok', 'Kayıp pet uyarısı kapatıldı.');
    }
}
