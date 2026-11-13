<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\PreauthBlock;
use App\Models\Reservation;
use App\Services\PreAuthPaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

class PreAuthController extends Controller
{
    public function index(Request $request, Reservation $reservation): RedirectResponse
    {
        $this->authorizeReservation($request, $reservation);

        return redirect()->to(route('panel.reservations.show', $reservation).'#on-otorizasyon');
    }

    public function store(Request $request, Reservation $reservation, PreAuthPaymentService $service): RedirectResponse
    {
        $this->authorizeReservation($request, $reservation);

        $data = $request->validate([
            'amount' => 'required|numeric|min:1|max:1000000',
            'holder' => 'required|string|max:120',
            'number' => 'required|digits_between:12,19',
            'expire_month' => 'required|digits_between:1,2',
            'expire_year' => 'required|digits:4',
            'cvc' => 'required|digits_between:3,4',
        ]);

        try {
            $service->block(
                $reservation,
                (int) round(((float) $data['amount']) * 100),
                $data,
                [
                    'id' => (string) $reservation->user_id,
                    'name' => (string) $reservation->user->name,
                    'surname' => '',
                    'email' => (string) $reservation->user->email,
                    'address' => 'Antalya',
                    'city' => 'Antalya',
                    'ip' => $request->ip(),
                ]
            );
        } catch (RuntimeException $e) {
            return back()->withErrors(['preauth' => $e->getMessage()]);
        }

        return back()->with('ok', 'Ön otorizasyon blokajı oluşturuldu.');
    }

    public function capture(Request $request, PreauthBlock $block, PreAuthPaymentService $service): RedirectResponse
    {
        $this->authorizeBlock($request, $block);

        try {
            $service->capture($block);
        } catch (RuntimeException $e) {
            return back()->withErrors(['preauth' => $e->getMessage()]);
        }

        return back()->with('ok', 'Ön otorizasyon tahsil edildi.');
    }

    public function release(Request $request, PreauthBlock $block, PreAuthPaymentService $service): RedirectResponse
    {
        $this->authorizeBlock($request, $block);
        $service->release($block, (int) $request->user()->id);

        return back()->with('ok', 'Blokaj serbest bırakıldı; tutar iyzico tarafından otomatik iade edilir.');
    }

    public function callback(Request $request, Reservation $reservation): RedirectResponse
    {
        $this->authorizeReservation($request, $reservation);

        return redirect()->route('panel.care.show', $reservation)
            ->with('ok', '3D Secure ön otorizasyon akışı tamamlandı.');
    }

    private function authorizeReservation(Request $request, Reservation $reservation): void
    {
        $user = $request->user();
        $hotel = $reservation->hotel;
        abort_unless($user->isAdmin() || (int) $hotel->owner_id === (int) $user->id, 403);
    }

    private function authorizeBlock(Request $request, PreauthBlock $block): void
    {
        $user = $request->user();
        abort_unless($user->isAdmin() || (int) $block->hotel->owner_id === (int) $user->id, 403);
    }
}
