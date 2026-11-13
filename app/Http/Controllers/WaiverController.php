<?php
namespace App\Http\Controllers;
use App\Models\Reservation;
use App\Models\Waiver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
class WaiverController extends Controller
{
    public function store(Request $request, Reservation $reservation)
    {
        abort_unless((int) $reservation->user_id === (int) $request->user()->id, 403);
        abort_unless(in_array($reservation->status, ['pending', 'confirmed'], true), 409);
        $data = $request->validate([
            'waiver_type' => ['required', 'in:veterinary_intervention,aggressive_pet,kvkk'],
            'signature_data' => ['required', 'string', 'max:200000', 'regex:/^data:image\/(png|svg\+xml);base64,/'],
            'emergency_budget' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'accepted' => ['accepted'],
        ]);
        $version = '2026-10-23-v1';
        $content = implode('|', [$data['waiver_type'], $version, (string) $reservation->id, (string) $reservation->pet_id, (string) ($data['emergency_budget'] ?? '')]);
        DB::transaction(function () use ($request, $reservation, $data, $version, $content): void {
            Waiver::updateOrCreate(
                ['reservation_id' => $reservation->id, 'waiver_type' => $data['waiver_type'], 'version' => $version],
                [
                    'user_id' => $request->user()->id,
                    'pet_id' => $reservation->pet_id,
                    'content_hash' => hash('sha256', $content),
                    'signature_data' => $data['signature_data'],
                    'emergency_budget' => $data['emergency_budget'] ?? null,
                    'ip_address' => $request->ip(),
                    'user_agent' => Str::limit((string) $request->userAgent(), 1000, ''),
                    'signed_at' => now(),
                ]
            );
        });
        return back()->with('ok', 'Dijital onayınız güvenli şekilde kaydedildi.');
    }
}