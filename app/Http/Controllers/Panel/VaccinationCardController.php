<?php
declare(strict_types=1);
namespace App\Http\Controllers\Panel;
use App\Http\Controllers\Controller;
use App\Models\Pet;
use App\Models\VaccinationCard;
use App\Services\VaccinationCardOcrService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
class VaccinationCardController extends Controller
{
    public function store(Request $request, Pet $pet, VaccinationCardOcrService $ocr): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->isAdmin() || (int) $pet->user_id === (int) $user->id, 403);
        $data = $request->validate(['card' => 'required|file|mimes:jpg,jpeg,png,pdf|max:10240']);
        $stored = $ocr->store($data['card']);
        $extracted = $ocr->extract($stored);
        VaccinationCard::create([
            'pet_id' => $pet->id,
            'hotel_id' => null,
            'uploaded_by' => $user->id,
            'path' => $stored['path'],
            'original_name' => $stored['original_name'],
            'mime' => $stored['mime'],
            'size' => $stored['size'],
            'ocr_result' => $extracted,
            'extracted_vaccines' => $extracted['vaccines'] ?? [],
        ]);
        return back()->with('ok', 'Aşı kartı yüklendi ve doğrulama kuyruğuna alındı.');
    }
}