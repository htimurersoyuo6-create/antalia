<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Reservation;
use App\Services\LateCheckoutService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class LateCheckoutController extends Controller
{
    public function approve(Request $request, Reservation $reservation, LateCheckoutService $lateCheckout): RedirectResponse
    {
        $this->authorizeHotel($request, $reservation);

        try {
            $lateCheckout->approve($reservation, $request->user());
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return back()->with('ok', 'Geç çıkış talebi onaylandı ve ücret rezervasyona eklendi.');
    }

    public function decline(Request $request, Reservation $reservation, LateCheckoutService $lateCheckout): RedirectResponse
    {
        $this->authorizeHotel($request, $reservation);

        try {
            $lateCheckout->decline($reservation);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return back()->with('ok', 'Geç çıkış talebi reddedildi.');
    }

    private function authorizeHotel(Request $request, Reservation $reservation): void
    {
        $user = $request->user();

        abort_unless(
            $user->isAdmin() || (int) $reservation->hotel->owner_id === (int) $user->id,
            403
        );
    }
}
