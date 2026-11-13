<?php

/**
 * SEO yardimcilari: schema.org uretimi ve meta metin olcusu.
 *
 * Bu dosya composer.json autoload.files ile yuklenir. Bu yuzden:
 *   - namespace KULLANILMAZ (blade global cagirir)
 *   - e() ve route() sadece view render aninda guvenlidir
 *   - kacis icin htmlspecialchars kullanilir
 *
 * ONEMLI: hicbir blade satirinda "@context" yazma. Blade o
 * metni yonergede sanip derliyor ve gecersiz JSON uretiyor.
 * @context anahtari sema_bolum() icinde eklenir.
 */

if (!function_exists('seo_metin')) {
    /**
     * HTML duz metne cevirir, kirpar ve olcer.
     * Meta description ve og:description icin kullanilir.
     */
    function seo_metin(?string $s, int $n = 160, bool $kirp = true): string
    {
        $s = strip_tags((string) $s);
        $s = html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $s = preg_replace('~\s+~u', ' ', $s) ?? $s;
        $s = trim($s);
        if (!mb_check_encoding($s, 'UTF-8')) {
            $s = mb_convert_encoding($s, 'UTF-8', 'UTF-8');
        }
        if ($kirp && mb_strlen($s) > $n) {
            $kesit = mb_substr($s, 0, $n - 1);
            $sinir = (int) floor($n * 0.55);
            $cumleSinirlari = array_filter([
                mb_strrpos($kesit, '. '),
                mb_strrpos($kesit, '? '),
                mb_strrpos($kesit, '! '),
            ], static fn ($konum) => $konum !== false && $konum >= $sinir);
            if ($cumleSinirlari) {
                $kesit = mb_substr($kesit, 0, max($cumleSinirlari) + 1);
            } else {
                $bosluk = mb_strrpos($kesit, ' ');
                if ($bosluk !== false && $bosluk >= $sinir) $kesit = mb_substr($kesit, 0, $bosluk);
            }
            $s = rtrim($kesit, " ,;:-");
            if (!preg_match('~[.!?…]$~u', $s)) $s .= '.';
        }
        return $s;
    }
}

if (!function_exists('blog_gorsel_havuzu')) {
    /** public/img/blog altindaki gorsel adlarini (uzantisiz) dondurur. */
    function blog_gorsel_havuzu(): array
    {
        static $liste = null;
        if ($liste !== null) return $liste;
        $liste = [];
        $dizin = public_path('img/blog');
        if (is_dir($dizin)) {
            foreach ((array) scandir($dizin) as $dosya) {
                if (!preg_match('~^([a-z0-9][a-z0-9-]*)\.(?:webp|jpg|jpeg|png)$~i', (string) $dosya, $m)) continue;
                $liste[strtolower($m[1])] = true;
            }
        }
        $liste = array_keys($liste);
        sort($liste);
        return $liste;
    }
}

if (!function_exists('blog_gorsel_puanla')) {
    /** Baslik/slug metnine gore gorselleri alakaya gore puanlar.
     *  Nadir gecen kelimeler (asi, kisirlastirma, tasma...) yuksek,
     *  cok yaygin kelimeler (kedi, kopek, bakimi...) dusuk agirlik alir. */
    function blog_gorsel_puanla(string $metin): array
    {
        $metin = mb_strtolower($metin, 'UTF-8');
        $metin = strtr($metin, [
            'ı'=>'i','İ'=>'i','ş'=>'s','Ş'=>'s','ğ'=>'g','Ğ'=>'g','ü'=>'u','Ü'=>'u',
            'ö'=>'o','Ö'=>'o','ç'=>'c','Ç'=>'c','â'=>'a','Â'=>'a','î'=>'i','Î'=>'i','û'=>'u','Û'=>'u',
        ]);
        $metin = preg_replace('~[^a-z0-9]+~', ' ', $metin) ?? '';
        $stop = ['ile','icin','nasil','neden','nedir','hangi','daha','bir','ve','ama','veya','gibi',
                 'kadar','sonra','once','olarak','olan','temel','genel','rehberi','rehber'];
        $kelimeler = array_values(array_unique(array_filter(
            explode(' ', $metin),
            fn($k) => mb_strlen((string) $k) >= 4 && !in_array($k, $stop, true)
        )));

        $havuz = blog_gorsel_havuzu();
        $parca = [];
        $df = [];
        foreach ($havuz as $ad) {
            $ps = array_values(array_unique(array_filter(
                explode('-', $ad),
                fn($p) => mb_strlen((string) $p) >= 3
            )));
            $parca[$ad] = $ps;
            foreach ($ps as $p) $df[$p] = ($df[$p] ?? 0) + 1;
        }
        $agirlik = static function (string $p) use ($df): int {
            $d = $df[$p] ?? 1;
            if ($d >= 40) return 1;
            if ($d >= 20) return 2;
            if ($d >= 10) return 3;
            if ($d >= 5)  return 5;
            return 8;
        };

        $puan = [];
        foreach ($havuz as $ad) {
            $s = 0;
            foreach ($parca[$ad] as $p) {
                $w = $agirlik($p);
                foreach ($kelimeler as $k) {
                    if ($k === $p) { $s += $w; break; }
                    if (mb_strlen($k) >= 5 && mb_strlen($p) >= 5
                        && (str_contains($k, $p) || str_contains($p, $k))) { $s += 2; break; }
                }
            }
            if ($s > 0) $puan[$ad] = $s;
        }
        arsort($puan);
        return $puan;
    }
}

if (!function_exists('blog_gorsel_url')) {
    /** Gorsel adindan (uzantisiz) tam URL uretir; dosya yoksa null. */
    function blog_gorsel_url(string $ad): ?string
    {
        if (!preg_match('~^[a-z0-9][a-z0-9-]*$~', $ad)) return null;
        foreach (['webp', 'jpg', 'jpeg', 'png'] as $uzanti) {
            if (is_file(public_path("img/blog/{$ad}.{$uzanti}"))) return asset("img/blog/{$ad}.{$uzanti}");
        }
        return null;
    }
}

if (!function_exists('blog_cover_asset')) {
    function blog_cover_asset(string $slug, string $baslik = ''): string
    {
        $guvenli = preg_match('/^[a-z0-9-]+$/D', $slug) ? $slug : '';
        if ($guvenli !== '') {
            $birebir = blog_gorsel_url($guvenli);
            if ($birebir !== null) return $birebir;
        }

        foreach (array_keys(blog_gorsel_puanla($guvenli . ' ' . $baslik)) as $ad) {
            $u = blog_gorsel_url($ad);
            if ($u !== null) return $u;
        }

        $fallbacklar = [
            'pet-ev-otel-alternatifi'   => 'evcil-hayvan-ile-tatil-planlamasi',
            'pet-otel-oda-tipleri'      => 'pet-otel-tesis-kriterleri',
            'pet-otel-araba-kiralama'   => 'kedi-ile-seyahat',
            'yasli-kopek-konaklama'     => 'yasli-kedi-kopek-bakimi-2026',
            'yasli-kedi-konaklama'      => 'yasli-kedi-kopek-bakimi-2026',
            'pet-otel-enerji-tasarrufu' => 'pet-otel-hijyen',
        ];
        $ad = $fallbacklar[$guvenli] ?? 'pet-otel-secim-rehberi';
        $u = blog_gorsel_url($ad);
        return $u ?? asset('img/og-default.png');
    }
}

if (!function_exists('seo_slug')) {
    /** Turkce karakterleri ASCII karsiliklarina indirger, slug uretir. */
    function seo_slug(string $s): string
    {
        $harf = [
            'ı' => 'i', 'İ' => 'i', 'ş' => 's', 'Ş' => 's', 'ğ' => 'g', 'Ğ' => 'g',
            'ü' => 'u', 'Ü' => 'u', 'ö' => 'o', 'Ö' => 'o', 'ç' => 'c', 'Ç' => 'c',
            'â' => 'a', 'Â' => 'a', 'î' => 'i', 'Î' => 'i', 'û' => 'u', 'Û' => 'u',
        ];
        $s = strtr($s, $harf);
        $s = mb_strtolower($s, 'UTF-8');
        $s = preg_replace('~[^a-z0-9]+~', '-', $s) ?? '';
        return trim($s, '-');
    }
}

if (!function_exists('seo_zaman')) {
    /** Zaman damgasini ISO-8601 (schema.org lastmod) bicimine cevirir. */
    function seo_zaman(mixed $t): ?string
    {
        if ($t === null || $t === '') return null;
        if ($t instanceof DateTimeInterface) return $t->format('c');
        try {
            return (new DateTimeImmutable((string) $t))->format('c');
        } catch (Throwable) {
            return null;
        }
    }
}

if (!function_exists('sema_bolum')) {
    /**
     * schema.org dugumunu kurar.
     * @context buraya gomulur; blade icinde hic yazilmaz.
     */
    function sema_bolum(string $type, array $ozellik, array $ekstra = []): array
    {
        return array_merge(
            ['@context' => 'https://schema.org', '@type' => $type],
            $ozellik,
            $ekstra
        );
    }
}

if (!function_exists('sema_ld')) {
    /**
     * Diziyi guvenli <script> bloguna cevirir.
     *
     * JSON_HEX_TAG sayesinde < ve > karakterleri kod noktasina
     * cevrilir; boylece script blogu erken kapatilamaz.
     */
    function sema_ld(?array $veri): string
    {
        if (!$veri) return '';
        $json = json_encode(
            $veri,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
            | JSON_PRETTY_PRINT
        );
        if ($json === false || $json === '') return '';
        return '<script type="application/ld+json">' . $json . '</script>';
    }
}

if (!function_exists('sema_kirinti')) {
    /**
     * BreadcrumbList uretir.
     * Giris: [['ad' => 'Ana Sayfa', 'url' => 'https://...'], ...]
     * Son ogede url verilmez (mevcut sayfa).
     */
    function sema_kirinti(array $adimlar): string
    {
        $oge = [];
        $sira = 1;
        foreach ($adimlar as $a) {
            $ad = trim((string) ($a['ad'] ?? ''));
            if ($ad === '') continue;
            $og = ['@type' => 'ListItem', 'position' => $sira++, 'name' => $ad];
            if (!empty($a['url'])) $og['item'] = $a['url'];
            $oge[] = $og;
        }
        if (!$oge) return '';
        return sema_ld(sema_bolum('BreadcrumbList', ['itemListElement' => $oge]));
    }
}

if (!function_exists('sema_sss')) {
    /**
     * Visible FAQs are rendered in HTML; FAQ rich results are no longer emitted.
     */
    function sema_sss(array $sss): string
    {
        return '';
    }
}

if (!function_exists('seo_kurum')) {
    /**
     * Site geneli Organization + WebSite semasi.
     * Gercek adres/telefon bilinmedigi uydurulmaz; eklendigi yerde
     * guvenli sekilde kullanilir.
     */
    function seo_kurum(array $ayar = []): string
    {
        $ad = (string) ($ayar['ad'] ?? \App\Models\Setting::al('site_name', 'Pet Otel'));
        $tel = trim((string) ($ayar['telefon'] ?? ''));
        $eposta = trim((string) ($ayar['eposta'] ?? ''));
        $adres = trim((string) ($ayar['adres'] ?? ''));
        $siteCity = trim((string) \App\Models\Setting::al('site_city', ''));
        $defaultDescription = ($siteCity !== '' ? $siteCity : $ad) . ' bölgesindeki kedi ve köpek otellerini konum, oda tipi ve fiyat bilgileriyle karşılaştırın.';

        $k = [
            'name' => $ad,
            'url' => rtrim((string) ($ayar['url'] ?? config('app.url')), '/'),
            'description' => seo_metin($ayar['aciklama'] ?? $defaultDescription, 300, false),
        ];
        /* Logo isteğe bağlıdır; özel logo yüklenmemişse marka adı kullanılır. */
        $logo = trim((string) ($ayar['logo'] ?? ''));
        if ($logo !== '') {
            $k['logo'] = (str_contains($logo, '://') || str_starts_with($logo, '/'))
                ? ['@type' => 'ImageObject', 'url' => $logo, 'width' => 512, 'height' => 512]
                : $logo;
        }
        $telE164 = trim((string) ($ayar['tel_e164'] ?? ''));
        if ($telE164 !== '')          $k['telephone'] = $telE164;
        elseif ($tel !== '')          $k['telephone'] = $tel;
        if ($eposta !== '')           $k['email'] = $eposta;
        if ($adres !== '') {
            /* schema.org PostalAddress alanlari:
             *   streetAddress    : cadde/sokak
             *   addressLocality  : ilce
             *   addressRegion    : il
             *   postalCode       : posta kodu
             *   addressCountry   : ulke kodu
             * Google NAP dogrulamasinda bu alanlar tek tek eslesir. */
            $k['address'] = array_filter([
                '@type' => 'PostalAddress',
                'streetAddress' => $adres,
                'addressLocality' => trim((string) ($ayar['ilce'] ?? '')) ?: null,
                'addressRegion' => trim((string) ($ayar['sehir'] ?? '')) ?: null,
                'postalCode' => trim((string) ($ayar['posta'] ?? '')) ?: null,
                'addressCountry' => 'TR',
            ]);
        }
        if ($tel !== '' || $eposta !== '') {
            $k['contactPoint'] = array_filter([
                '@type' => 'ContactPoint',
                'contactType' => 'customer support',
                'telephone' => $tel ?: null,
                'email' => $eposta ?: null,
                'availableLanguage' => ['tr', 'en'],
                'areaServed' => 'TR',
            ]);
        }

        $website = [
            'name' => $ad,
            'url' => rtrim((string) ($ayar['url'] ?? config('app.url')), '/'),
            'inLanguage' => 'tr-TR',
        ];
        // Kurulumda logo isteğe bağlıdır. URL'si boş ImageObject üretmeyiz.
        if ($logo !== '') {
            $website['logo'] = [
                '@type' => 'ImageObject',
                'url' => (str_contains($logo, '://') || str_starts_with($logo, '/'))
                    ? $logo : asset($logo),
                'width' => 512,
                'height' => 512,
            ];
        }

        return sema_ld(sema_bolum('Organization', $k))
             . sema_ld(sema_bolum('WebSite', $website));
    }
}

if (!function_exists('seo_otel_sema')) {
    /**
     * Otel detay sayfasinin schema.org paketi:
     *   LocalBusiness + BreadcrumbList
     *
     * $h      otel kaydi (Eloquent veya stdClass)
     * $adimlar breadcrumb adimlari
     * $sss    FAQ ciftleri
     */
    function seo_otel_sema(object $h, array $adimlar = [], array $sss = [], bool $includeContact = true): string
    {
        $url = route('hotels.show', $h->slug);
        /* Yalnızca sayfada kullanılan gerçek kapak/galeri adreslerini ekle.
         * Yer tutucu pati ikonunu tesis fotoğrafı gibi işaretlemeyiz. */
        $resimler = [];
        $kapak = method_exists($h, 'primaryPhotoUrl')
            ? trim((string) ($h->primaryPhotoUrl() ?? ''))
            : trim((string) ($h->kapak_url ?? ''));
        if ($kapak !== '' && !str_contains($kapak, '/img/paw-placeholder.svg')) {
            $resimler[] = $kapak;
        }
        foreach ((array) ($h->galeri ?? []) as $g) {
            $g = (string) $g;
            if ($g !== '') $resimler[] = $g;
        }
        $resimler = array_values(array_unique(array_filter($resimler)));
        $resimler = array_slice($resimler, 0, 20);

        $adres = array_filter([
            '@type' => 'PostalAddress',
            'streetAddress' => trim((string) ($h->address ?? '')) ?: null,
            'addressLocality' => trim((string) ($h->district->name ?? '')) ?: trim((string) ($h->city->name ?? '')),
            'addressRegion' => trim((string) ($h->city->name ?? '')) ?: null,
            'addressCountry' => 'TR',
        ]);

        $ozellik = [
            'name' => (string) $h->name,
            'description' => seo_metin($h->description ?? '', 900, false),
            'url' => $url,
            'address' => $adres ?: null,
            'telephone' => $includeContact ? (trim((string) ($h->phone ?? '')) ?: null) : null,
            'image' => $resimler ?: null,
            'petsAllowed' => trim((string) (($h->accepts_dogs ? 'Kopek' : '')
                . ($h->accepts_dogs && $h->accepts_cats ? ', ' : '')
                . ($h->accepts_cats ? 'Kedi' : ''))) ?: null,
            'currenciesAccepted' => 'TRY',
        ];

        if ($h->lat !== null && $h->lng !== null && (float) $h->lat !== 0.0) {
            $ozellik['geo'] = ['@type' => 'GeoCoordinates',
                'latitude' => (float) $h->lat, 'longitude' => (float) $h->lng];
        }
        if (!empty($h->google_maps_url)) $ozellik['hasMap'] = (string) $h->google_maps_url;
        if (!empty($h->sameAs)) $ozellik['sameAs'] = $h->sameAs;
        /* Yorum yoksa AggregateRating YAZILMAZ. Google sahte yorum
         * tespit eder ve guvenilirligi dusurur. */
        if ((int) ($h->rating_count ?? 0) > 0) {
            $ozellik['aggregateRating'] = [
                '@type' => 'AggregateRating',
                'ratingValue' => (float) $h->rating_avg,
                'reviewCount' => (int) $h->rating_count,
                'bestRating' => 5,
                'worstRating' => 1,
            ];
        }

        // Emit business schema only when the listing has a verified license
        // and a usable street address; unverified listings remain ordinary pages.
        unset($ozellik['petsAllowed']);
        $verifiedAddress = !empty($h->license_verified_at) && trim((string) ($h->address ?? '')) !== '';
        $donus = $verifiedAddress ? sema_ld(sema_bolum('LocalBusiness', array_filter($ozellik))) : '';
        if ($adimlar) $donus .= sema_kirinti($adimlar);
        return $donus;
    }
}

if (!function_exists('seo_liste_sema')) {
    /**
     * ItemList + CollectionPage semasi (kategori ve ilce sayfalari).
     * Giris: [['url'=>..., 'ad'=>...], ...]
     */
    function seo_liste_sema(string $ad, string $url, string $aciklama, array $oge): string
    {
        $liste = [];
        $sira = 1;
        foreach ($oge as $o) {
            $u = trim((string) ($o['url'] ?? ''));
            if ($u === '') continue;
            $liste[] = [
                '@type' => 'ListItem',
                'position' => $sira++,
                'url' => $u,
                'name' => trim((string) ($o['ad'] ?? $u)),
            ];
        }
        if (!$liste) return '';
        return sema_ld(sema_bolum('CollectionPage', [
            'name' => $ad,
            'url' => $url,
            'description' => seo_metin($aciklama, 300, false),
            'inLanguage' => 'tr-TR',
            'mainEntity' => [
                '@type' => 'ItemList',
                'itemListElement' => $liste,
                'numberOfItems' => count($liste),
            ],
        ], ['breadcrumb' => ['@id' => $url . '#breadcrumb']]));
    }
}

if (!function_exists('seo_yazi_sema')) {
    /** Blog yazisi icin BlogPosting + Breadcrumb semasi. */
    function seo_yazi_sema(object $p, array $adimlar = []): string
    {
        /* Yazar tipi: "Antalya Pet Otel Editor" bir kurumun
         * editoryel adi; gercek bir kisi DEGIL. Google Person
         * tipinde gercek kisi bekler, bu yuzden Organization
         * kullanilir ve ayni @id ile baglanir. */
        $siteUrl = rtrim((string) url('/'), '/');
        $kurum   = [
            '@type' => 'Organization',
            'name'  => \App\Models\Setting::al('site_name', 'Pet Otel'),
            'url'   => $siteUrl,
            '@id'   => $siteUrl . '#organization',
            'logo'  => [
                '@type'  => 'ImageObject',
                'url'    => \App\Models\Setting::al('site_logo')
                    ? asset('storage/' . \App\Models\Setting::al('site_logo'))
                    : asset('img/paw-placeholder.svg'),
                'width'  => 512,
                'height' => 512,
            ],
        ];

        /* Yapılandırılmış veride de makale sayfasında gösterilen kapak görseli kullanılır. */
        $slug  = (string) ($p->slug ?? '');
        $kapak = $slug !== '' ? blog_cover_asset($slug, (string) ($p->title ?? '')) : null;

        $yazi = [
            'headline' => seo_metin($p->title ?? '', 110, false),
            'description' => seo_metin($p->excerpt ?: strip_tags((string) ($p->body ?? '')), 250, false),
            'url' => route('blog.show', $p->slug),
            'mainEntityOfPage' => ['@type' => 'WebPage', '@id' => route('blog.show', $p->slug)],
            'inLanguage' => 'tr-TR',
            'datePublished' => seo_zaman($p->published_at ?? null),
            'dateModified' => seo_zaman($p->updated_at ?? null),
            'publisher' => $kurum,
            'image' => $kapak,
            /* aggregateRating EKLENMEYECEK: hotels.rating_count 0,
             * reviews tablosunda yayinda yorum yok. Sifir puan
             * veya Google Business puanlarini kullanici yorumu
             * gibi gostermek policy ihlali olurdu. */
        ];
        $donus = sema_ld(sema_bolum('BlogPosting', $yazi));
        if ($adimlar) $donus .= sema_kirinti($adimlar);
        return $donus;
    }
}

if (!function_exists('sayfa_sss_cikar')) {
    /**
     * Sayfa govdesinden SSS ciftlerini cikarir.
     *
     * Kabul edilen kaliplar:
     *   <h2>Soru?</h2><p>Cevap...</p>
     *   <h3>Soru?</h3><p>Cevap</p>
     *   <strong>Soru?</strong> Cevap
     *   "Soru?" Cevap   (tire isaretli liste)
     *
     * Yalniz SORU ISARETIYLE BITEN basliklar alinir; boylece
     * "Otel Kurallari" gibi basliklar SSS sayilmaz.
     */
    function sayfa_sss_cikar(string $govde): array
    {
        $out = [];

        /* 1) baslik + paragraf */
        if (preg_match_all('~<h([23])[^>]*>([^<]*)</h\1>\s*<(p|ul|ol)[^>]*>(.*?)</\3>~is',
            $govde, $m, PREG_SET_ORDER)) {
            foreach ($m as $b) {
                $s = seo_metin($b[2], 200, false);
                $c = seo_metin($b[4], 900, false);
                if ($s === '' || $c === '') continue;
                if (!preg_match('~[?？]$~u', $s)) continue;
                $out[] = ['soru' => $s, 'cevap' => $c];
            }
        }

        /* 2) guvenli kalip / strong */
        if (!$out && preg_match_all('~<(?:strong|b)[^>]*>([^<]*\?[^\x{3C}]*)</(?:strong|b)>\s*([^<]{20,})~iu',
            $govde, $m2, PREG_SET_ORDER)) {
            foreach ($m2 as $b) {
                $s = seo_metin($b[1], 200, false);
                $c = seo_metin($b[2], 900, false);
                if ($s === '' || $c === '') continue;
                $out[] = ['soru' => $s, 'cevap' => $c];
            }
        }

        return $out;
    }
}
