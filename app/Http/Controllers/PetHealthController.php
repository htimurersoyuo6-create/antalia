<?php

namespace App\Http\Controllers;

use App\Models\Pet;
use App\Models\GeofencedAlert;
use App\Models\PetMedicalDocument;
use App\Models\PetVaccination;
use App\Models\Reservation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Crypt;

class PetHealthController extends Controller
{
    private function ownedPet(Request $request, Pet $pet): Pet
    {
        abort_unless((int) $pet->user_id === (int) $request->user()->id, 404);
        return $pet;
    }

    public function index(Request $request, Pet $pet)
    {
        $this->ownedPet($request, $pet);
        $documents = $pet->medicalDocuments()->latest()->get();
        $vaccinations = $pet->vaccinations()->orderByRaw('next_due_at IS NULL')->orderBy('next_due_at')->get();
        $lostAlerts = GeofencedAlert::open()->where('pet_id', $pet->id)->latest('reported_at')->get();
        return view('pets.health', compact('pet', 'documents', 'vaccinations', 'lostAlerts'));
    }

    public function uploadDocument(Request $request, Pet $pet)
    {
        $this->ownedPet($request, $pet);
        $data = $request->validate([
            'title' => 'required|string|max:120',
            'document_type' => 'required|in:health_card,passport,vaccination_record,other',
            'document' => 'required|file|mimes:pdf,jpg,jpeg,png,webp|max:10240',
            'share_with_active_hotel' => 'nullable|boolean',
        ]);
        $file = $data['document'];
        $path = 'pet-health/'.$request->user()->id.'/'.$pet->id.'/'.\Illuminate\Support\Str::uuid().'.enc';
        Storage::disk('local')->put($path, Crypt::encryptString(file_get_contents($file->getRealPath())));
        $pet->medicalDocuments()->create([
            'user_id' => $request->user()->id,
            'title' => $data['title'],
            'document_type' => $data['document_type'],
            'disk_path' => $path,
            'original_name' => basename($file->getClientOriginalName()),
            'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
            'size_bytes' => $file->getSize(),
            'share_with_active_hotel' => $request->boolean('share_with_active_hotel'),
            'encrypted' => true,
        ]);
        return back()->with('ok', 'Belge güvenli şekilde kaydedildi. Varsayılan olarak yalnızca siz görebilirsiniz.');
    }

    public function downloadDocument(Request $request, PetMedicalDocument $document)
    {
        $isOwner = (int) $document->user_id === (int) $request->user()->id;
        $isAuthorizedHotel = false;
        if (!$isOwner && $request->user()->isOwner() && $document->share_with_active_hotel) {
            $isAuthorizedHotel = $document->pet->reservations()
                ->whereIn('hotel_id', $request->user()->hotels()->select('id'))
                ->whereIn('status', ['confirmed', 'checked_in', 'in_progress'])
                ->whereDate('check_in', '<=', today())
                ->whereDate('check_out', '>=', today())
                ->exists();
        }
        abort_unless($isOwner || $isAuthorizedHotel, 404);
        abort_unless(Storage::disk('local')->exists($document->disk_path), 404);
        return $this->documentResponse($document);
    }

    public function deleteDocument(Request $request, PetMedicalDocument $document)
    {
        abort_unless((int) $document->user_id === (int) $request->user()->id, 404);
        Storage::disk('local')->delete($document->disk_path);
        $document->delete();
        return back()->with('ok', 'Belge silindi.');
    }

    public function downloadForReservation(Request $request, Reservation $reservation, PetMedicalDocument $document)
    {
        abort_unless($request->user()->hotels()->whereKey($reservation->hotel_id)->exists(), 404);
        abort_unless($reservation->pet_id && (int) $document->pet_id === (int) $reservation->pet_id, 404);
        abort_unless((int) $document->user_id === (int) $reservation->user_id && $document->share_with_active_hotel && in_array($reservation->status, ['pending', 'confirmed'], true) && $reservation->check_out->gte(today()), 404);
        abort_unless(Storage::disk('local')->exists($document->disk_path), 404);
        return $this->documentResponse($document);
    }

    private function documentResponse(PetMedicalDocument $document)
    {
        if (!$document->encrypted) {
            return Storage::disk('local')->download($document->disk_path, $document->original_name, ['Content-Type' => $document->mime_type, 'X-Content-Type-Options' => 'nosniff']);
        }
        $bytes = Crypt::decryptString(Storage::disk('local')->get($document->disk_path));
        return response($bytes, 200, [
            'Content-Type' => $document->mime_type,
            'Content-Disposition' => 'attachment; filename="'.str_replace(['"', "\\", "\r", "\n"], '_', $document->original_name).'"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function storeVaccination(Request $request, Pet $pet)
    {
        $this->ownedPet($request, $pet);
        $data = $request->validate([
            'vaccine_name' => 'required|string|max:120',
            'vaccine_type' => 'required|in:rabies,combined,other',
            'vaccine_type' => 'required|in:rabies,combined,other',
            'administered_at' => 'nullable|date|before_or_equal:today',
            'next_due_at' => 'nullable|date|after_or_equal:administered_at',
            'clinic_name' => 'nullable|string|max:160',
            'vet_name' => 'nullable|string|max:120',
            'batch_number' => 'nullable|string|max:80',
            'notes' => 'nullable|string|max:1000',
            'reminder_enabled' => 'nullable|boolean',
        ]);
        $data['user_id'] = $request->user()->id;
        $data['reminder_enabled'] = $request->boolean('reminder_enabled', true);
        $pet->vaccinations()->create($data);
        return back()->with('ok', 'Aşı kaydı ve hatırlatma tercihi eklendi. Takvim öneri niteliğindedir; tarihi veterinerinizle doğrulayın.');
    }

    public function updateVaccination(Request $request, PetVaccination $vaccination)
    {
        abort_unless((int) $vaccination->user_id === (int) $request->user()->id, 404);
        $data = $request->validate([
            'vaccine_name' => 'required|string|max:120',
            'administered_at' => 'nullable|date|before_or_equal:today',
            'next_due_at' => 'nullable|date|after_or_equal:administered_at',
            'clinic_name' => 'nullable|string|max:160',
            'vet_name' => 'nullable|string|max:120',
            'batch_number' => 'nullable|string|max:80',
            'notes' => 'nullable|string|max:1000',
            'reminder_enabled' => 'nullable|boolean',
        ]);
        $data['reminder_enabled'] = $request->boolean('reminder_enabled');
        $vaccination->update($data);
        return back()->with('ok', 'Aşı takvimi güncellendi.');
    }

    public function deleteVaccination(Request $request, PetVaccination $vaccination)
    {
        abort_unless((int) $vaccination->user_id === (int) $request->user()->id, 404);
        $vaccination->delete();
        return back()->with('ok', 'Aşı kaydı silindi.');
    }
}
