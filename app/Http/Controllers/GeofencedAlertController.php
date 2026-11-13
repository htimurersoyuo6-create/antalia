<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\GeofencedAlert;
use App\Models\Pet;
use App\Services\GeofencedAlertService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GeofencedAlertController extends Controller
{
    public function index(): View
    {
        $alerts = GeofencedAlert::query()
            ->open()
            ->where('is_public', true)
            ->whereHas('pet')
            ->with('pet:id,name,species,breed,size')
            ->latest('reported_at')
            ->paginate(12);

        return view('lost-pets.index', compact('alerts'));
    }

    public function show(GeofencedAlert $alert): View
    {
        abort_unless($alert->is_public && $alert->status === 'open', 404);
        $alert->load('pet:id,name,species,breed,size');
        abort_unless($alert->pet, 404);

        return view('lost-pets.show', [
            'alert' => $alert,
            'approxLat' => number_format(round((float) $alert->lat, 2), 2, '.', ''),
            'approxLng' => number_format(round((float) $alert->lng, 2), 2, '.', ''),
        ]);
    }

    public function store(Request $request, Pet $pet, GeofencedAlertService $service): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->isAdmin() || (int) $pet->user_id === (int) $user->id, 403);

        if (GeofencedAlert::open()->where('pet_id', $pet->id)->exists()) {
            return back()->withErrors(['pet' => 'Bu dost için zaten açık bir kayıp bildirimi var. Önce mevcut bildirimi kapatın.']);
        }

        $phoneRule = $request->boolean('share_phone')
            ? 'required|string|max:30|regex:/^\+?[0-9\s().-]{7,30}$/'
            : 'nullable|string|max:30|regex:/^\+?[0-9\s().-]{7,30}$/';
        $data = $request->validate([
            'lat' => 'required|numeric|between:-90,90',
            'lng' => 'required|numeric|between:-180,180',
            'radius_m' => 'nullable|integer|min:500|max:50000',
            'note' => 'nullable|string|max:500',
            'public_listing' => 'sometimes|accepted',
            'share_phone' => 'sometimes|accepted',
            'public_phone' => $phoneRule,
        ]);

        $isPublic = $request->boolean('public_listing');
        if ($request->boolean('share_phone') && !$isPublic) {
            return back()->withInput()->withErrors(['share_phone' => 'Telefon paylaşımı için önce ilanı herkese açık yapın.']);
        }
        $alert = $service->report(
            $pet,
            (float) $data['lat'],
            (float) $data['lng'],
            (int) ($data['radius_m'] ?? 5000),
            $data['note'] ?? null,
            $isPublic,
            $isPublic && $request->boolean('share_phone') ? $data['public_phone'] : null,
        );

        if ($isPublic) {
            return redirect()->route('lost-pets.show', $alert)
                ->with('ok', 'Kayıp ilanı paylaşıldı. Konum yaklaşık gösterilir; telefon yalnızca ayrıca izin verdiğiniz için görünür.');
        }

        return back()->with('ok', 'Kayıp pet bildirimi açıldı. '.count($alert->notified_user_ids ?? []).' uygun kullanıcıya bildirim gönderildi.');
    }

    public function resolve(Request $request, GeofencedAlert $alert, GeofencedAlertService $service): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->isAdmin() || (int) $alert->pet?->user_id === (int) $user->id, 403);
        $service->resolve($alert, (int) $user->id);

        return back()->with('ok', 'Kayıp pet uyarısı kapatıldı.');
    }
}
