<?php
namespace App\Http\Controllers;

use App\Models\District;
use App\Models\Veterinary;

class VeterinaryController extends Controller
{
    public function index()
    {
        $ilce = (string) request('ilce', '');
        $q    = trim((string) request('q', ''));

        $sorgu = Veterinary::published();
        if ($ilce !== '') {
            $sorgu->whereHas('district', fn ($x) => $x->where('slug', $ilce));
        }
        if ($q !== '') {
            $sorgu->where(function ($x) use ($q) {
                $x->where('name', 'like', "%$q%")
                  ->orWhere('address', 'like', "%$q%")
                  ->orWhere('description', 'like', "%$q%");
            });
        }

        $vets = $sorgu->orderByDesc('featured')
            ->orderBy('sort')->orderBy('name')
            ->paginate(12)->withQueryString();

        $ilceler = District::query()
            ->whereIn('id', Veterinary::published()
                ->whereNotNull('district_id')->select('district_id')->distinct()->toBase())
            ->orderBy('name')->get();

        return view('veterinaries.index', compact('vets', 'ilceler', 'ilce', 'q'));
    }

    public function show(string $slug)
    {
        $vet = Veterinary::published()->where('slug', $slug)->firstOrFail();

        $benzer = Veterinary::published()
            ->where('district_id', $vet->district_id)
            ->where('id', '!=', $vet->id)
            ->orderByDesc('featured')->orderBy('sort')->orderBy('name')
            ->limit(6)->get();

        return view('veterinaries.show', compact('vet', 'benzer'));
    }
}
