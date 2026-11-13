<?php
namespace App\Http\Controllers;

use App\Models\Pet;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PetController extends Controller
{
    private function rules(): array
    {
        return [
            'name' => 'required|string|max:80',
            'species' => 'required|in:dog,cat,other',
            'microchip_number' => ['nullable', 'regex:/^[0-9]{15}$/', Rule::unique('pets', 'microchip_number')->ignore(request()->route('pet'))],
            'breed' => 'nullable|string|max:80',
            'size' => 'required|in:small,medium,large',
            'birth_year' => 'nullable|integer|min:1995|max:'.date('Y'),
            'vaccinated' => 'nullable|boolean',
            'vaccination_expires_at' => 'nullable|date|after_or_equal:1995-01-01',
            'notes' => 'nullable|string|max:2000',
            'emergency_contact_name' => 'nullable|string|max:120',
            'emergency_contact_relationship' => 'nullable|string|max:80',
            'emergency_contact_phone' => 'nullable|string|max:40',
            'emergency_contact_2_name' => 'nullable|string|max:120',
            'emergency_contact_2_relationship' => 'nullable|string|max:80',
            'emergency_contact_2_phone' => 'nullable|string|max:40',
            'vet_name' => 'nullable|string|max:120',
            'vet_clinic' => 'nullable|string|max:160',
            'vet_phone' => 'nullable|string|max:40',
            'vet_address' => 'nullable|string|max:255',
        ];
    }

    public function index(Request $r) {
        $user=$r->user();
        $pets=$user->pets()->with('vaccinations')->orderBy('name')->get();
        $vaccineReminders = $pets->flatMap(fn ($pet) => $pet->vaccinations
            ->filter(fn ($vaccine) => $vaccine->reminder_enabled && $vaccine->next_due_at && $vaccine->next_due_at->lte(today()->addDays(30)))
            ->map(fn ($vaccine) => ['pet' => $pet, 'vaccine' => $vaccine]))
            ->sortBy(fn ($item) => $item['vaccine']->next_due_at)
            ->values();
        $reservations=$user->reservations()->with(['hotel','room','pet'])->latest()->limit(6)->get();
        $stats=[
            'pets'=>$pets->count(),
            'active'=>$user->reservations()->whereIn('status',['pending','confirmed'])->count(),
            'upcoming'=>$user->reservations()->whereIn('status',['pending','confirmed'])->whereDate('check_in','>=',today())->count(),
            'transfers'=>$user->reservations()->where('transfer_status','in_progress')->count(),
        ];
        return view('pets.index',compact('pets','reservations','stats','vaccineReminders'));
    }
    public function create() { return view('pets.form', ['pet' => new Pet()]); }

    public function store(Request $r)
    {
        $d = $r->validate($this->rules());
        $d['vaccinated'] = $r->boolean('vaccinated');
        $d['microchip_number'] = preg_replace('/\D+/', '', (string) ($d['microchip_number'] ?? '')) ?: null;
        $r->user()->pets()->create($d);
        return redirect()->route('pets.index')->with('ok', 'Evcil hayvan profili kaydedildi.');
    }

    public function edit(Request $r, Pet $pet) { abort_unless($pet->user_id === $r->user()->id, 403); return view('pets.form', compact('pet')); }

    public function update(Request $r, Pet $pet)
    {
        abort_unless($pet->user_id === $r->user()->id, 403);
        $d = $r->validate($this->rules());
        $d['vaccinated'] = $r->boolean('vaccinated');
        $d['microchip_number'] = preg_replace('/\D+/', '', (string) ($d['microchip_number'] ?? '')) ?: null;
        $pet->update($d);
        return redirect()->route('pets.index')->with('ok', 'Profil güncellendi.');
    }

    public function destroy(Request $r, Pet $pet)
    {
        abort_unless($pet->user_id === $r->user()->id, 403);
        if ($pet->reservations()->exists()) return back()->withErrors(['pet' => 'Rezervasyonu olan bir profil silinemez.']);
        foreach ($pet->medicalDocuments as $document) \Illuminate\Support\Facades\Storage::disk('local')->delete($document->disk_path);
        $pet->delete();
        return redirect()->route('pets.index')->with('ok', 'Profil silindi.');
    }
}
