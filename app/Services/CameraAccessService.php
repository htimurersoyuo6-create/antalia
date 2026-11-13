<?php
namespace App\Services;
use App\Models\Reservation;
use Illuminate\Auth\Access\AuthorizationException;
class CameraAccessService {
    public function assertAvailable(Reservation $reservation): void {
        if ($reservation->status !== 'confirmed' || !$reservation->check_in || !$reservation->check_out || now()->lt($reservation->check_in->startOfDay()) || now()->gte($reservation->check_out->startOfDay())) {
            throw new AuthorizationException('Canlı kamera yalnızca devam eden onaylı konaklamalarda kullanılabilir.');
        }
    }
    public function available(Reservation $reservation): bool {
        try {$this->assertAvailable($reservation); return true;} catch (AuthorizationException) {return false;}
    }
}
