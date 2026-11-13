<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Hotel;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;

class BranchController extends Controller
{
    private function ownedRoot(Request $request, Hotel $hotel): Hotel
    {
        abort_unless((int) $hotel->owner_id === (int) $request->user()->id, 403);
        abort_if($hotel->parent_hotel_id !== null, 404);
        return $hotel;
    }

    public function index(Request $request, Hotel $hotel)
    {
        $root = $this->ownedRoot($request, $hotel);
        $branches = $root->branches()->with(['city', 'district'])->orderBy('name')->get();
        $availableHotels = Hotel::query()->where('owner_id', $request->user()->id)
            ->whereNull('parent_hotel_id')->where('id', '<>', $root->id)
            ->with(['city', 'district'])->orderBy('name')->get();

        return view('panel.branches.index', compact('root', 'branches', 'availableHotels'));
    }

    public function attach(Request $request, Hotel $hotel)
    {
        $root = $this->ownedRoot($request, $hotel);
        $data = $request->validate(['branch_id' => ['required', 'integer', 'not_in:'.(string) $hotel->id, 'exists:hotels,id']]);

        DB::transaction(function () use ($request, $root, $data) {
            $branch = Hotel::query()->whereKey($data['branch_id'])->lockForUpdate()->firstOrFail();
            if ((int) $branch->owner_id !== (int) $request->user()->id || $branch->parent_hotel_id !== null || $branch->is($root)) {
                throw ValidationException::withMessages(['branch_id' => 'Yalnızca size ait, başka bir zincire bağlı olmayan tesis eklenebilir.']);
            }
            $branch->parent_hotel_id = $root->id;
            $branch->save();
        });

        return back()->with('success', 'Şube zincire bağlandı.');
    }

    public function detach(Request $request, Hotel $hotel, Hotel $branch)
    {
        $root = $this->ownedRoot($request, $hotel);
        abort_unless((int) $branch->owner_id === (int) $request->user()->id && (int) $branch->parent_hotel_id === (int) $root->id, 404);
        $branch->parent_hotel_id = null;
        $branch->save();

        return back()->with('success', 'Şube zincir bağlantısı kaldırıldı. Tesis kaydı ve rezervasyonları korunuyor.');
    }
}
