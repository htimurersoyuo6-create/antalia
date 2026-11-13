<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Hotel extends Model
{
    /**
     * Rotalarda (panel/yonetim) model binding icin slug kullan.
     * URL uretimi de otomatik olarak slug kullanir.
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function resolveRouteBindingQuery($query, $value, $field = null)
    {
        return is_numeric($value) ? $query->whereKey($value) : $query->where($field ?: 'slug', $value);
    }

    public function resolveRouteBinding($value, $field = null)
    {
        return $this->resolveRouteBindingQuery($this->newQuery(), $value, $field)->first();
    }

    protected $guarded = [];
    protected $casts = ['has_daycare' => 'boolean', 'accepts_dogs' => 'boolean', 'accepts_cats' => 'boolean', 'camera_enabled' => 'boolean', 'camera_embed_url' => 'encrypted', 'subscription_suspended' => 'boolean', 'room_info_confirmed_at' => 'datetime'];
    public function city() { return $this->belongsTo(City::class); }
    public function district() { return $this->belongsTo(District::class); }
    public function owner() { return $this->belongsTo(User::class, 'owner_id'); }
    public function parentHotel() { return $this->belongsTo(self::class, 'parent_hotel_id'); }
    public function branches() { return $this->hasMany(self::class, 'parent_hotel_id'); }
    public function favoritedByUsers() { return $this->belongsToMany(User::class, 'hotel_favorites')->withTimestamps(); }
    public function rooms() { return $this->hasMany(RoomType::class); }
    public function reservations() { return $this->hasMany(Reservation::class); }
    public function reviews() { return $this->hasMany(Review::class); }

    /** Seeder'in olusturdugu yer tutucu sahip hesabi; bu hesaba bagli oteller henuz sahiplenilmemis demektir. */
    public const YER_TUTUCU_SAHIP = 'ornek@antalyapetotel.com';
    public function sahipsizMi(): bool { return strtolower((string) $this->owner?->email) === self::YER_TUTUCU_SAHIP; }
    public function extras() { return $this->hasMany(HotelExtra::class); }
    public function discounts() { return $this->hasMany(HotelDiscount::class); }
    public function shopProducts() { return $this->hasMany(ShopProduct::class); }
    public function shopOrders() { return $this->hasMany(ShopOrder::class); }
    public function subscriptions() { return $this->hasMany(HotelSubscription::class); }
    public function paymentTransactions() { return $this->hasMany(PaymentTransaction::class); }
    public function transferZoneRates() { return $this->hasMany(TransferZoneRate::class); }
    public function currentSubscription() { return $this->hasOne(HotelSubscription::class)->current()->latestOfMany(); }

    /** Panelde isaretlenen standart olanaklar (listedeki filtrelerle anahtar sozcukleri uyumlu). */
    public const STANDART_OLANAKLAR = [
        '7/24 kamera izleme', 'Veteriner desteği', 'İlaç uygulaması', 'Günlük video raporu', 'Fotoğraf albümü',
        'Oyun alanı', 'Geniş bahçe', 'Kafessiz konaklama', 'Bireysel konaklama', 'Grup konaklama',
        'Bireysel bakım', 'Grup bakım', 'Özel gezdirme', 'Klima', 'Isıtma', 'Doğal ışık',
        'Günlük dezenfeksiyon', 'Karantina odası', 'Pet transfer / taksi', 'Pet kuaför / yıkama', 'Köpek eğitimi',
    ];
    public function claims() { return $this->hasMany(HotelClaim::class); }

    public function scopeActive($q) { return $q->where('status', 'active'); }
    public function scopeForPet($q, ?string $type)
    {
        return match ($type) {
            'kopek-otelleri' => $q->where('accepts_dogs', true),
            'kedi-otelleri'  => $q->where('accepts_cats', true),
            default => $q,
        };
    }
    public function getCoverUrlAttribute(): ?string
    {
        if (!$this->cover_path) return null;
        return \Illuminate\Support\Facades\Storage::disk('public')->exists($this->cover_path)
            ? asset('storage/'.$this->cover_path)
            : null;
    }
    /**
     * En dusuk aktif oda fiyati (kartta "X TL / geceden" gosterilir).
     *
     * DIKKAT: bu accessor ONCEDEN YOKTU. _card.blade.php bu alani
     * kullaniyordu ama modelde tanimli olmadigi icin kosul her
     * zaman false idi ve fiyat hic gosterilmedi.
     *
     * Iliskiler onceden yuklendiyse bellekten okur (N+1 sorgu
     * olusmaz); yuklenmediyse tek sorgu yapar.
     */
    public function getRoomsMinBasePriceAttribute(): ?float
    {
        $selectedPrice = $this->getAttributes()['rooms_min_base_price'] ?? null;
        if ($selectedPrice !== null) {
            return (float) $selectedPrice > 0 ? (float) $selectedPrice : null;
        }

        if ($this->relationLoaded('rooms')) {
            $en = $this->rooms->min(fn($r) => (float) $r->base_price);
            return ($en !== null && $en > 0) ? (float) $en : null;
        }
        $en = $this->rooms()->where('base_price', '>', 0)->min('base_price');
        return ($en !== null && (float) $en > 0) ? (float) $en : null;
    }

    /**
     * Tesisin gercekten odasi olan turler.
     *
     * accepts_dogs / accepts_cats bayraklari isletme beyanidir ve
     * ikisi de 1 olabilir; ancak odalarinin cogu tek tur olabilir.
     * Kullanici "kedi odasi var mi" diye soruyor, bu yuzden cevap
     * ODA TIPLERINDEN turetilir.
     *
     * Donus: 'kedi' | 'kopek' | 'ikisi' | 'diger'
     */
    public function turEtiketi(): string
    {
        $turler = $this->relationLoaded('rooms')
            ? $this->rooms->pluck('pet_type')->filter()->unique()->values()
            : $this->rooms()->distinct()->pluck('pet_type')->filter();

        $cat  = $turler->contains('cat');
        $dog  = $turler->contains('dog');
        if ($cat && $dog)  return 'ikisi';
        if ($cat)          return 'kedi';
        if ($dog)          return 'kopek';

        /* oda tipi yoksa isletme beyanina dus */
        if ($this->accepts_cats && !$this->accepts_dogs) return 'kedi';
        if ($this->accepts_dogs && !$this->accepts_cats) return 'kopek';
        if ($this->accepts_dogs && $this->accepts_cats)  return 'ikisi';
        return 'diger';
    }
    /**
     * Galeri gorsellerinin tam yollari (photos kolonundaki JSON).
     * Kapak gorseli listenin basinda degilse de ayri bir alan
     * olarak tutulur; liste yalniz ek gorselleri icerir.
     */
    public function getGaleriAttribute(): array
    {
        $ham = trim((string) ($this->photos ?? ''));
        if ($ham === '') return [];
        $j = json_decode($ham, true);
        if (!is_array($j)) return [];
        $out = [];
        foreach ($j as $yol) {
            $yol = trim((string) $yol);
            if ($yol === '') continue;
            /* dis adresleri guvenlik icin reddet */
            if (preg_match('~^(https?:)?//~i', $yol)) continue;
            $yol = ltrim($yol, '/');
            if (\Illuminate\Support\Facades\Storage::disk('public')->exists($yol)) {
                $out[] = asset('storage/' . $yol);
            }
        }
        return $out;
    }

    public function hasCoverImage(): bool
    {
        if (!empty($this->cover_path) && \Illuminate\Support\Facades\Storage::disk('public')->exists($this->cover_path)) {
            return true;
        }

        $slug = (string) $this->slug;
        if ($slug === '' || !preg_match('~^[a-z0-9-]+$~i', $slug)) {
            return false;
        }

        $base = public_path('img/otel/' . $slug);
        return is_file($base . '.jpg') || is_file($base . '.webp');
    }

    public function primaryPhotoUrl(): ?string
    {
        if ($this->hasCoverImage()) return $this->kapak_url;
        return $this->galeri[0] ?? null;
    }

    /** Kapak + galeri birlikte (semada ImageObject uretimi icin). */
    public function tumGorseller(): array
    {
        $g = [];
        if ($this->cover_path) $g[] = asset('storage/' . $this->cover_path);
        foreach ($this->galeri as $x) $g[] = $x;
        return array_values(array_unique($g));
    }
    public function refreshRating(): void
    {
        $agg = $this->reviews()->where('status', 'published')->selectRaw('count(*) c, avg(rating) a')->first();
        $this->update(['rating_count' => (int) $agg->c, 'rating_avg' => round((float) $agg->a, 2)]);
    }

    /**
     * Karta, otel sayfasina ve og:image'a gidecek kapak adresi.
     *
     * cover_path doluysa işletmenin yüklediği GERÇEK fotoğraf
     * kullanılır. Boşsa public/img/otel/{slug}.jpg içindeki, o tesise
     * özgü marka kapağı döner. Böylece hiçbir kart boş kalmaz;
     * işletme panelden fotoğraf yüklediğinde gerçek fotoğraf
     * kendiliğinden öne geçer.
     */
    public function getKapakUrlAttribute(): string
    {
        if (!empty($this->cover_path) && \Illuminate\Support\Facades\Storage::disk('public')->exists($this->cover_path)) {
            return asset('storage/' . $this->cover_path);
        }
        $slug = (string) $this->slug;
        if ($slug !== '' && preg_match('~^[a-z0-9-]+$~i', $slug)) {
            foreach (['jpg', 'webp'] as $extension) {
                $path = public_path('img/otel/' . $slug . '.' . $extension);
                if (is_file($path)) {
                    return asset('img/otel/' . $slug . '.' . $extension);
                }
            }
        }
        return asset('img/logo-kapak.png');
    }
}
