<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Herkese acik GET sayfalari icin tarayici/on-ici onbellegi.
 *
 * ONCE
 *   Tum sayfalar "cache-control: no-cache, private" donuyordu;
 *   her ziyaret PHP'ye iniyordu.
 *
 * KURALLAR (guvenlik oncelikli)
 *   UygulanMAZ:
 *     - oturum acmis kullanici
 *     - herhangi bir oturum verisi (flash mesaj, hata, dil)
 *     - POST / PUT / PATCH / DELETE
 *     - giris, kayit, cikis, panel, yonetim ve kullanici sayfalari
 *     - 3xx / 4xx / 5xx yanitlar
 *
 * Uygulanir:
 *   herkese acik GET sayfalari -> public, s-maxage + stale-while-revalidate
 *
 * NOT
 *   Yanitta Vary: Cookie eklenmez; cunku oturum varsa zaten
 *   onbelleklenmiyor. Ancak Laravel session cookie'i her istekte
 *   gonderildigi icin CDN katmani kural olarak sayfayi
 *   oturum yokken de paylasabilir.
 */
class PublicCache
{
    /* Onbelleklenmeyecek yol onekleri */
    private const HARIC = [
        'panel', 'yonetim', 'giris', 'kayit', 'cikis',
        'rezervasyonlarim', 'evcil-hayvanlarim', 'up',
        'api', 'storage', 'sohbet', 'sohbetlerim',
    ];

    public function handle(Request $r, Closure $sonraki)
    {
        $yanit = $sonraki($r);

        if (!$this->uygun($r, $yanit)) {
            return $yanit;
        }

        $yol = trim($r->path(), '/');
        if ($r->routeIs('hotels.show')) {
            /* Hotel detail facts and layout change as owners update listings.
             * Keep edge copies short and never serve a stale detail page while
             * revalidating, so room/booking UI updates reach visitors promptly. */
            $yanit->headers->set('Cache-Control', 'public, max-age=0, s-maxage=60, must-revalidate');
        } else {
            $saniye = $this->sure($r);
            $yanit->headers->set('Cache-Control',
                'public, max-age=' . min($saniye, 300)
                . ', s-maxage=' . $saniye
                . ', stale-while-revalidate=86400');
        }

        /* Onunde CDN varsa oturumlu kullaniciya herkese acik
         * onbellekli sayfa servis edilmesin. */
        $vary = $yanit->headers->get('Vary');
        if ($vary === null || !str_contains(strtolower($vary), 'cookie')) {
            $yanit->headers->set('Vary', trim(($vary ?? '') . ' Cookie'));
        }
        return $yanit;
    }

        private function uygun(Request $r, $yanit): bool
    {
        if (!$r->isMethod('GET') && !$r->isMethod('HEAD')) return false;
        /* Fresh post-logout landing must not reuse or create a shared page copy. */
        if ($r->boolean('signed_out')) return false;
        if (method_exists($yanit, 'getStatusCode') && $yanit->getStatusCode() >= 300) return false;

        /* Tarihli musaitlik sonuclari ani degisir; paylasilan onbellege girmesin */
        if ($r->query->has('giris') || $r->query->has('cikis')) return false;

        $yol = trim($r->path(), '/');
        if ($yol === 'partner/tesis-basvurularim') return false; /* private bearer-token application status */
        if (str_ends_with($yol, '/teklif') || str_contains('/' . $yol, '/sohbet')) return false;   /* anlik fiyat JSON'u */
        if ($yol === '') return true;
        $ilk = explode('/', $yol)[0];
        if (in_array($ilk, self::HARIC, true)) return false;

        /* Oturum acmis kullanici: kişisel icerik, onbelleklenmez */
        try {
            if (auth()->check()) return false;
        } catch (\Throwable) {
            return false;
        }

        /* Oturum BASLATILMAMISSA dokunma; yeni baslatma.
         * Baslatilmissa yalnizca framework anahtarlari haric
         * gercek veri olup olmadigina bak.
         *
         * _token      : CSRF belirteci (her sayfada olabilir)
         * _previous_url, _previous : geri donus bilgisi
         * _flash      : framework isareti
         *
         * Bunlar kisisel icerik DEGILDIR; bulunmasi sayfayi
         * onbelleklenemez yapmamalidir. */
        try {
            $oturum = $r->hasSession() ? $r->session() : null;
            if ($oturum !== null && $oturum->isStarted()) {
                $veri = $oturum->all();
                foreach (['_token', '_previous_url', '_previous', '_flash'] as $anahtar) {
                    unset($veri[$anahtar]);
                }
                if (count($veri) > 0) return false;   /* flash mesaj / hata var */
            }
        } catch (\Throwable) {
            return false;
        }

        return true;
    }
private function sure(Request $r): int
    {
        $yol = '/' . trim($r->path(), '/');
        if (str_starts_with($yol, '/sitemap.xml')) return 3600;
        if (str_starts_with($yol, '/blog/'))     return 1800;
        if (str_starts_with($yol, '/ilce/'))     return 3600;
        if (str_starts_with($yol, '/kategori/')) return 1800;
        if ($yol === '/ara') return 600;
        return 1800;
    }
}
