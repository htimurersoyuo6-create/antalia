<?php
namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\City;
use App\Models\Hotel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class HotelController extends Controller
{
    private function own(Request $r, Hotel $hotel): Hotel
    {
        abort_unless($hotel->owner_id === $r->user()->id, 403);
        return $hotel;
    }

    public function edit(Request $r, Hotel $hotel)
    {
        return $this->renderEditor($r, $hotel, 'rooms');
    }

    public function section(Request $r, Hotel $hotel, string $section)
    {
        abort_unless(in_array($section, ['widget', 'rooms', 'extras', 'campaigns'], true), 404);
        return $this->renderEditor($r, $hotel, $section);
    }

    private function renderEditor(Request $r, Hotel $hotel, string $section)
    {
        $this->own($r, $hotel)->load('rooms.priceRules', 'rooms.inventoryUnits.reservations.user', 'rooms.inventoryUnits.reservations.pet', 'extras', 'discounts');
        $cities = City::with('districts')->orderBy('name')->get();
        return view('panel.hotel', compact('hotel', 'cities', 'section'));
    }

    public function update(Request $r, Hotel $hotel)
    {
        $this->own($r, $hotel);
        $d = $r->validate([
            'name' => 'required|string|max:160',
            'description' => 'nullable|string|max:5000',
            'policies' => 'nullable|string|max:3000',
            'city_id' => 'required|exists:cities,id',
            'district_id' => 'required|exists:districts,id',
            'address' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:30',
            'license_no' => 'nullable|string|max:80',
            'lat' => 'nullable|numeric|between:-90,90',
            'lng' => 'nullable|numeric|between:-180,180',
            'cover' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
            'photos_new' => 'nullable|array|max:8',
            'photos_new.*' => 'image|mimes:jpg,jpeg,png,webp|max:5120',
            'photos_remove' => 'nullable|array',
            'photos_remove.*' => 'string|max:255',
            'olanak' => 'nullable|array',
            'olanak.*' => 'string|max:80',
            'olanak_diger' => 'nullable|string|max:900',
            'camera_enabled' => 'nullable|boolean',
            'camera_embed_url' => ['nullable','url','max:2048',function($a,$v,$fail){if($v && parse_url($v,PHP_URL_SCHEME)!=='https')$fail('Canlı kamera bağlantısı güvenli HTTPS olmalı.');}],
            'transfer_enabled' => 'nullable|boolean',
            'transfer_mode' => 'required|in:free,paid',
            'transfer_price_km' => 'nullable|numeric|min:0|max:10000',
            'transfer_price_pet' => 'nullable|numeric|min:0|max:10000',
            'transfer_instructions' => 'nullable|string|max:500',
            'shop_enabled' => 'nullable|boolean',
            'shop_fulfillment' => 'required|in:pickup,delivery,both',
            'shop_delivery_fee' => 'nullable|numeric|min:0|max:100000',
            'shop_free_delivery_min' => 'nullable|numeric|min:0|max:1000000',
        ]);
        abort_unless(\App\Models\District::whereKey($d['district_id'])->where('city_id', $d['city_id'])->exists(), 422, 'Seçilen ilçe seçilen ile ait değil.');
        $d['accepts_dogs'] = $r->boolean('accepts_dogs');
        $d['accepts_cats'] = $r->boolean('accepts_cats');
        $d['has_daycare'] = $r->boolean('has_daycare');
        $d['camera_enabled'] = $r->boolean('camera_enabled');
        $d['transfer_enabled'] = $r->boolean('transfer_enabled');
        $d['shop_enabled'] = $r->boolean('shop_enabled');
        $d['shop_delivery_fee'] = (float) ($d['shop_delivery_fee'] ?? 0);
        $d['shop_free_delivery_min'] = ($d['shop_free_delivery_min'] ?? '') !== '' ? $d['shop_free_delivery_min'] : null;
        $d['transfer_price_km'] = $d['transfer_mode'] === 'paid' ? ($d['transfer_price_km'] ?? 0) : 0;
        $d['transfer_price_pet'] = $d['transfer_mode'] === 'paid' ? ($d['transfer_price_pet'] ?? 0) : 0;
        $d['camera_embed_url'] = trim((string)($d['camera_embed_url'] ?? '')) ?: null;
        if ($d['camera_enabled'] && !$d['camera_embed_url']) return back()->withErrors(['camera_embed_url'=>'Kamerayı açmak için HTTPS yayın bağlantısı girin.'])->withInput();
        if ($hotel->license_no !== ($d['license_no'] ?? null)) $d['license_verified_at'] = null;
        if ($r->hasFile('cover')) {
            $oldCoverPath = $hotel->cover_path;
            $d['cover_path'] = $this->gorselKaydet($r->file('cover'), 'hotels');
            if ($oldCoverPath) Storage::disk('public')->delete($oldCoverPath);
        }
        unset($d['cover']);

        /* Galeri: mevcut listeyi oku, seçilenleri sil, yenileri ekle (en fazla 12 fotoğraf) */
        $liste = json_decode((string) ($hotel->photos ?? ''), true);
        $liste = is_array($liste) ? array_values(array_filter($liste, 'is_string')) : [];
        foreach ((array) $r->input('photos_remove', []) as $yol) {
            $i = array_search($yol, $liste, true);
            if ($i !== false && str_starts_with($yol, 'hotels/')) {
                Storage::disk('public')->delete($yol);
                unset($liste[$i]);
            }
        }
        $liste = array_values($liste);
        foreach ((array) $r->file('photos_new', []) as $dosya) {
            if (count($liste) >= 12) break;
            $liste[] = $this->gorselKaydet($dosya, 'hotels/' . $hotel->slug);
        }
        $d['photos'] = $liste ? json_encode($liste, JSON_UNESCAPED_SLASHES) : null;
        unset($d['photos_new'], $d['photos_remove']);
        /* Olanaklar: katalogdan isaretlenenler (dogrulanir) + serbest "diger" olanaklar */
        $secim = array_values(array_intersect(Hotel::STANDART_OLANAKLAR, (array) $r->input('olanak', [])));
        $diger = collect(explode(',', (string) $r->input('olanak_diger', '')))
            ->map(fn ($x) => mb_substr(trim($x), 0, 60))->filter()->take(15)->all();
        $amListe = array_values(array_unique(array_merge($secim, $diger)));
        $d['amenities'] = $amListe ? json_encode($amListe, JSON_UNESCAPED_UNICODE) : null;
        unset($d['olanak'], $d['olanak_diger']);
        $hotel->update($d);
        return back()->with('ok', 'Otel bilgileri güncellendi.');
    }

    public function confirmRoomInfo(Request $r, Hotel $hotel)
    {
        $this->own($r, $hotel);
        abort_unless($hotel->rooms()->exists(), 422, 'Önce en az bir oda tipi ve fiyat ekleyin.');

        $hotel->forceFill(['room_info_confirmed_at' => now()])->save();

        return back()->with('ok', 'Oda ve fiyat bilgilerini kontrol edip doğruladınız. Bu tarih misafirlere gösterilir.');
    }

    /** Fotografi kucultup WebP'ye cevirerek saklar; GD yoksa orijinali oldugu gibi saklar. */
    private function gorselKaydet(\Illuminate\Http\UploadedFile $dosya, string $klasor): string
    {
        return \App\Support\ImageOptimizer::storeUploaded($dosya, 'public', $klasor, 'cover');
    }
}
