<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Pet;
use App\Models\Reservation;
use App\Models\ReservationPet;
use App\Services\LateCheckoutService;
use App\Services\MultiPetService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Validation\ValidationException;

class ReservationExtrasController extends Controller
{
    public function show(Request $request, Reservation $reservation, MultiPetService $multiPet): View
    {
        $this->authorizeReservation($request, $reservation);

        $pets = Pet::query()->where('user_id', $request->user()->id)->orderBy('name')->get();

        return view('reservations.extras', [
            'reservation' => $reservation->load(['hotel', 'pet']),
            'links' => $multiPet->pets($reservation),
            'pets' => $pets,
        ]);
    }

    public function addPet(Request $request, Reservation $reservation, MultiPetService $multiPet): RedirectResponse
    {
        $this->authorizeReservation($request, $reservation);

        $data = $request->validate([
            'pet_id' => ['required', 'integer', 'exists:pets,id'],
        ]);

        $pet = Pet::query()->findOrFail((int) $data['pet_id']);

        try {
            $multiPet->add($reservation, $pet);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return back()->with('ok', $pet->name.' rezervasyona eklendi.');
    }

    public function removePet(Request $request, Reservation $reservation, ReservationPet $link, MultiPetService $multiPet): RedirectResponse
    {
        $this->authorizeReservation($request, $reservation);

        try {
            $multiPet->remove($reservation, $link);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return back()->with('ok', 'Evcil hayvan rezervasyondan çıkarıldı.');
    }

    public function requestLateCheckout(Request $request, Reservation $reservation, LateCheckoutService $lateCheckout): RedirectResponse
    {
        $this->authorizeReservation($request, $reservation);

        $data = $request->validate([
            'hours' => ['required', 'integer', 'min:1', 'max:6'],
        ]);

        try {
            $lateCheckout->request($reservation, (int) $data['hours']);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return back()->with('ok', 'Geç çıkış talebiniz tesise iletildi.');
    }

    private function authorizeReservation(Request $request, Reservation $reservation): void
    {
        abort_unless((int) $reservation->user_id === (int) $request->user()->id, 403);
    }
}
