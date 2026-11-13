<?php

namespace App\Http\Controllers;

use App\Models\Hotel;
use App\Support\PhoneNumber;
use Illuminate\Http\Request;

/**
 * "Birden fazla otelden fiyat al": ziyaretçi bilgilerini bir kez girer, seçtiği tesislere
 * hazır WhatsApp mesajı oluşturulur. Sunucuya kişisel veri GÖNDERİLMEZ ve saklanmaz;
 * mesaj tarayıcıda hazırlanır, WhatsApp'a ziyaretçinin kendi hesabından gider.
 */
class QuoteController extends Controller
{
    public function index(Request $request)
    {
        $tur = in_array($request->query('tur'), ['kopek', 'kedi'], true) ? $request->query('tur') : '';

        $oteller = Hotel::active()
            ->where('slug', 'not like', 'ornek-%')
            ->with('district:id,name')
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'phone', 'district_id', 'accepts_dogs', 'accepts_cats'])
            ->filter(fn ($h) => PhoneNumber::normalize($h->phone) !== null) // WhatsApp için cep numarası şart
            ->map(fn ($h) => [
                'name' => $h->name,
                'slug' => $h->slug,
                'ilce' => (string) ($h->district->name ?? ''),
                'wa' => PhoneNumber::whatsapp($h->phone),
                'kopek' => (bool) $h->accepts_dogs,
                'kedi' => (bool) $h->accepts_cats,
            ])
            ->sortBy([['ilce', 'asc'], ['name', 'asc']])
            ->values();

        return view('quote.index', [
            'oteller' => $oteller,
            'ilceler' => $oteller->pluck('ilce')->filter()->unique()->sort()->values(),
            'tur' => $tur,
        ]);
    }
}
