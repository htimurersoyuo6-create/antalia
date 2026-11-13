<?php
namespace App\Http\Controllers;

use App\Models\AdoptionListing;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AdoptionController extends Controller
{
    public function index(Request $request)
    {
        $query = AdoptionListing::query()->where('status', 'active')->latest();
        if ($request->filled('species')) $query->where('species', $request->query('species'));
        if ($request->filled('city')) $query->where('city', 'like', '%'.mb_substr($request->query('city'), 0, 100).'%');
        if ($request->filled('q')) {
            $term = mb_substr($request->query('q'), 0, 100);
            $query->where(fn($builder) => $builder->where('pet_name', 'like', '%'.$term.'%')->orWhere('breed', 'like', '%'.$term.'%'));
        }
        return view('adoption.index', ['listings' => $query->paginate(12)->withQueryString()]);
    }

    public function show(AdoptionListing $listing)
    {
        abort_unless($listing->status === 'active' || auth()->id() === $listing->user_id || auth()->user()?->isAdmin(), 404);
        return view('adoption.show', compact('listing'));
    }

    public function create()
    {
        return view('adoption.create', ['listing' => new AdoptionListing()]);
    }

    public function store(Request $request)
    {
        if (!\App\Support\Captcha::check($request, 'adoption')) {
            return back()->withErrors(['captcha' => 'Güvenlik kontrolü başarısız. Yeni doğrulamayı tamamlayın.'])->withInput();
        }

        $data = $request->validate($this->listingRules());
        $photo = $data['photo'] ?? null;
        unset($data['photo']);
        $data['user_id'] = $request->user()->id;
        $data['status'] = 'pending';

        $listing = AdoptionListing::create($data);
        if ($photo) {
            $path = \App\Support\ImageOptimizer::storeUploaded($photo, 'local', 'adoption-listings/'.$listing->id, 'photo');
            if (!$path) {
                $listing->delete();
                return back()->withInput()->withErrors(['photo' => 'Fotoğraf yüklenemedi. Lütfen tekrar deneyin.']);
            }
            $listing->photo_path = $path;
            $listing->save();
        }
        return redirect()->route('adoption.mine')->with('ok', 'İlanınız incelemeye gönderildi. Onaylandıktan sonra yayına alınır.');
    }

    public function edit(Request $request, AdoptionListing $listing)
    {
        abort_unless($listing->user_id === $request->user()->id, 404);
        abort_if($listing->status === 'adopted', 409, 'Sahiplendirilmiş ilan düzenlenemez.');
        return view('adoption.create', compact('listing'));
    }

    public function update(Request $request, AdoptionListing $listing)
    {
        abort_unless($listing->user_id === $request->user()->id, 404);
        abort_if($listing->status === 'adopted', 409, 'Sahiplendirilmiş ilan düzenlenemez.');

        $data = $request->validate($this->listingRules(false));
        $photo = $data['photo'] ?? null;
        unset($data['photo']);
        $oldPhoto = $listing->photo_path;
        $newPhoto = null;

        if ($photo) {
            $newPhoto = \App\Support\ImageOptimizer::storeUploaded($photo, 'local', 'adoption-listings/'.$listing->id, 'photo');
            if (!$newPhoto) return back()->withInput()->withErrors(['photo' => 'Fotoğraf yüklenemedi. Lütfen tekrar deneyin.']);
        }

        $listing->fill($data);
        $listing->status = 'pending';
        $listing->approved_at = null;
        if ($newPhoto) $listing->photo_path = $newPhoto;
        try {
            $listing->save();
        } catch (\Throwable $error) {
            if ($newPhoto) Storage::disk('local')->delete($newPhoto);
            throw $error;
        }

        if ($newPhoto && $oldPhoto) Storage::disk('local')->delete($oldPhoto);
        return redirect()->route('adoption.mine')->with('ok', 'İlan güncellendi ve yeniden incelemeye gönderildi.');
    }

    public function photo(Request $request, AdoptionListing $listing)
    {
        abort_unless($listing->status === 'active' || $request->user()?->id === $listing->user_id || $request->user()?->isAdmin(), 404);
        abort_unless($listing->photo_path && Storage::disk('local')->exists($listing->photo_path), 404);
        $path = Storage::disk('local')->path($listing->photo_path);
        $mime = Storage::disk('local')->mimeType($listing->photo_path);
        abort_unless(in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true), 404);
        return response()->file($path, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline; filename="adoption-'.$listing->id.'.'.($mime === 'image/jpeg' ? 'jpg' : ($mime === 'image/png' ? 'png' : 'webp')).'"',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function mine(Request $request)
    {
        $listings = AdoptionListing::where('user_id', $request->user()->id)->latest()->paginate(15);
        return view('adoption.mine', compact('listings'));
    }

    public function close(Request $request, AdoptionListing $listing)
    {
        abort_unless($listing->user_id === $request->user()->id || $request->user()->isAdmin(), 404);
        abort_unless($listing->status === 'active', 409);
        $listing->update(['status' => 'adopted', 'adopted_at' => now()]);
        return back()->with('ok', 'İlan sahiplendirildi olarak kapatıldı.');
    }

    public function moderate(Request $request, AdoptionListing $listing)
    {
        abort_unless($request->user()->isAdmin(), 403);
        $data = $request->validate(['status' => 'required|in:active,rejected']);
        $listing->update(['status' => $data['status'], 'approved_at' => $data['status'] === 'active' ? now() : null]);
        return back()->with('ok', 'İlan durumu güncellendi.');
    }

    public function queue()
    {
        // İlanlar ayrı bir veritabanındadır; kullanıcılar ana veritabanında.
        // Eloquent with('user') ilişkiyi ilan veritabanında aradığı için yönetim 500 veriyordu.
        $listings = AdoptionListing::where('status', 'pending')->latest()->paginate(20);
        $owners = \App\Models\User::whereIn('id', $listings->getCollection()->pluck('user_id')->filter()->unique())
            ->get(['id', 'name', 'email', 'phone'])->keyBy('id');
        $listings->getCollection()->each(fn ($listing) => $listing->setRelation('user', $owners->get($listing->user_id)));
        return view('admin.adoption-queue', [
            'listings' => $listings,
        ]);
    }

    private function listingRules(bool $photoRequired = true): array
    {
        return [
            'pet_name' => 'required|string|max:100',
            'species' => 'required|in:cat,dog,other',
            'breed' => 'nullable|string|max:100',
            'age_months' => 'nullable|integer|min:0|max:600',
            'sex' => 'nullable|in:female,male,unknown',
            'vaccinated' => 'nullable|boolean',
            'city' => 'required|string|max:100',
            'district' => 'nullable|string|max:100',
            'description' => 'required|string|min:40|max:5000',
            'contact_phone' => 'nullable|string|max:30',
            'photo' => [$photoRequired ? 'required' : 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ];
    }
}
