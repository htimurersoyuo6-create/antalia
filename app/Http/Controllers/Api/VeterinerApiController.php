<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Veterinary;
use App\Models\District;
use App\Models\City;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class VeterinerApiController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255',
            'address' => 'nullable|string',
            'phone' => 'nullable|string|max:50',
            'website' => 'nullable|url|max:255',
            'lat' => 'nullable|numeric|between:-90,90',
            'lng' => 'nullable|numeric|between:-180,180',
            'google_maps_url' => 'nullable|url|max:2000',
            'google_place_id' => 'nullable|string|max:500',
            'rating_avg' => 'nullable|numeric|min:0|max:5',
            'rating_count' => 'nullable|integer|min:0',
            'opening_hours' => 'nullable|array',
            'photos' => 'nullable|array',
            'description' => 'nullable|string',
            'status' => 'nullable|in:active,inactive',
            'city_slug' => 'required|string',
            'district_slug' => 'nullable|string',
        ]);

        $city = City::where('slug', $data['city_slug'])->first();
        if (!$city) {
            return response()->json(['error' => 'City not found: ' . $data['city_slug']], 404);
        }

        $districtId = null;
        if (!empty($data['district_slug'])) {
            $district = District::where('slug', $data['district_slug'])
                ->where('city_id', $city->id)
                ->first();
            if ($district) {
                $districtId = $district->id;
            }
        }

        // google_place_id ile benzersizlik kontrolü
        $existing = null;
        if (!empty($data['google_place_id'])) {
            $existing = Veterinary::where('google_place_id', $data['google_place_id'])->first();
        }

        $slug = $data['slug'] ?? Str::slug($data['name']);
        $originalSlug = $slug;
        $i = 2;
        while (Veterinary::where('slug', $slug)
            ->where('id', '!=', $existing?->id ?? 0)
            ->exists()) {
            $slug = $originalSlug . '-' . $i++;
        }

        $veterinerData = [
            'city_id' => $city->id,
            'district_id' => $districtId,
            'name' => $data['name'],
            'slug' => $slug,
            'address' => $data['address'],
            'phone' => $data['phone'],
            'website' => $data['website'],
            'maps_url' => $data['google_maps_url'],
            'lat' => $data['lat'],
            'lng' => $data['lng'],
            'rating' => $data['rating_avg'],
            'review_count' => $data['rating_count'],
            'opening_hours' => $data['opening_hours'],
            'description' => $data['description'],
            'is_published' => ($data['status'] ?? 'active') === 'active',
            'google_place_id' => $data['google_place_id'],
            'source' => 'google_maps_scraper',
        ];

        if ($existing) {
            $existing->update($veterinerData);
            return response()->json(['message' => 'Updated', 'data' => $existing], 200);
        }

        $veteriner = Veterinary::create($veterinerData);
        return response()->json(['message' => 'Created', 'data' => $veteriner], 201);
    }

    public function index(Request $request)
    {
        $vets = Veterinary::with(['city', 'district'])
            ->when($request->city, fn($q) => $q->whereHas('city', fn($q2) => $q2->where('slug', $request->city)))
            ->when($request->district, fn($q) => $q->whereHas('district', fn($q2) => $q2->where('slug', $request->district)))
            ->paginate(20);

        return response()->json($vets);
    }
}
