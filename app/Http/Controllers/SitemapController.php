<?php
namespace App\Http\Controllers;

use App\Models\City;
use App\Models\AdoptionListing;
use App\Models\District;
use App\Models\ForumTopic;
use App\Models\Hotel;
use App\Models\Page;
use App\Models\Post;

/**
 * sitemap.xml - butun indekslenebilir adresleri DB'den uretir.
 *
 * KAPSAM
 *   - ana sayfa, arama, kategori kombinasyonlari
 *   - aktif oteller (demo ornek-* haric)
 *   - yayindaki yazilar (ornek-* haric)
 *   - oteli olan ilceler (/{slug})
 *   - pages tablosundaki tum sayfalar
 *
 * lastmod ISO-8601, priority ve changefreq verilir.
 * XSL stil sayfasi eklenir; tarayicida okunabilir tablo gosterir.
 */
class SitemapController extends Controller
{
    private const DEMO = 'ornek-%';
    private const FORUM_CHUNK = 10000;

    public function main()
    {
        $url = [];
        $siteCity = trim((string) \App\Models\Setting::al('site_city', '')) ?: 'Antalya';
        $siteCitySlug = \Illuminate\Support\Str::slug($siteCity);

        // Sitemap yalnızca kanonik URL ve gerçekten değiştiği bilinen lastmod
        // alanını taşır. Google priority/changefreq değerlerini kullanmaz.
        $ekle = static function (array &$url, string $loc, $mod = null): void {
            $url[] = array_filter([
                'loc' => $loc,
                'lastmod' => $mod ? seo_zaman($mod) : null,
            ]);
        };

        /* --- temel sayfalar --- */
        $ekle($url, route('home'));
        if ($siteCitySlug !== '' && Hotel::active()->whereHas('city', fn ($q) => $q->where('slug', $siteCitySlug))->exists()) {
            $ekle($url, route('hotels.all'));
            $ekle($url, route('hotels.dogs'));
            $ekle($url, route('hotels.cats'));
        }
        $ekle($url, route('blog.index'));
        $ekle($url, route('vets.index'));
        $ekle($url, route('fiyatlar'));
        $ekle($url, route('adoption.index'));
        $ekle($url, route('forum.index'));
        $ekle($url, route('partner.create'));
        $ekle($url, route('contact'));

        /* forum konulari sitemap-forum-{n}.xml dosyalarinda (sitemap basina en fazla 50.000 adres siniri) */
        try {
            if (\Illuminate\Support\Facades\Schema::connection('adoption')->hasTable('adoption_listings')) {
                foreach (AdoptionListing::query()->where('status', 'active')->orderBy('id')->get(['id', 'updated_at']) as $listing) {
                    $ekle($url, route('adoption.show', $listing), $listing->updated_at);
                }
            }
        } catch (\Illuminate\Database\QueryException|\PDOException $error) {
            \Illuminate\Support\Facades\Log::warning(
                'Adoption listings omitted from sitemap because the adoption database is unavailable.',
                ['exception' => $error::class],
            );
        }

        /* --- sehir sayfalari (birden fazla sehir varsa) --- */
        $ekle($url, route('hub.kategori'));
        $ekle($url, route('hub.yardim'));
        $ekle($url, route('hub.harita'));

        /* Oda tipi katalog sayfalari: yalnizca 2+ tesiste bulunan tipler.
         * Bu esik controller'daki ayni sarti paylasir; tek tesiste bulunan
         * tipler icin sayfa acilmaz (ince icerik riski). */
        $odaTipler = \Illuminate\Support\Facades\DB::table('room_types')
            ->join('hotels', 'hotels.id', '=', 'room_types.hotel_id')
            ->where('hotels.status', 'active')
            ->where('room_types.base_price', '>', 0)
            ->selectRaw('room_types.name as ad, COUNT(DISTINCT room_types.hotel_id) as adet')
            ->groupBy('room_types.name')
            ->having('adet', '>=', 2)
            ->get();
        foreach ($odaTipler as $ot) {
            $ekle($url, url('/oda-tipi/' . \Illuminate\Support\Str::slug($ot->ad)));
        }

        foreach (City::query()->orderBy('name')->get() as $c) {
            if ($c->slug === $siteCitySlug) continue;
            if (!Hotel::active()->where('city_id', $c->id)->exists()) continue;
            $ekle($url, route('hotels.index', $c->slug));
        }

        /* --- ilce sayfalari (oteli olan her ilce) --- */
        $ilceIds = Hotel::active()->select('district_id')->distinct()->toBase();
        foreach (District::query()->whereIn('id', $ilceIds)->orderBy('name')->get() as $ilce) {
            $ekle($url, url('/' . $ilce->slug));
        }

        /* --- mahalle/bölge sayfalari: yalnizca aktif tesisi olan ilcelerde --- */
        foreach (config('local_seo.localities', []) as $bolge) {
            $ilce = District::query()->where('slug', $bolge['district'])->first();
            if (!$ilce || !Hotel::active()->where('district_id', $ilce->id)->exists()) continue;
            $ekle($url, route('local-seo.show', [$bolge['district'], $bolge['slug']]));
        }

        /* --- oteller --- */
        foreach (Hotel::active()
            ->where('slug', 'not like', self::DEMO)
            ->orderBy('name')->get(['slug', 'updated_at']) as $h) {
            $ekle($url, route('hotels.show', $h->slug), $h->updated_at);
        }

        /* --- yayindaki yazilar --- */
        foreach (Post::published()
            ->where('slug', 'not like', self::DEMO)
            ->orderByDesc('published_at')
            ->get(['slug', 'updated_at', 'published_at']) as $p) {
            $ekle($url, route('blog.show', $p->slug), $p->updated_at);
        }
        if (\Illuminate\Support\Facades\Schema::hasTable('veterinaries')) {
            foreach (\App\Models\Veterinary::published()->orderBy('name')->get(['slug', 'updated_at']) as $v) { $ekle($url, route('vets.show', $v->slug), $v->updated_at); }
        }

        /* --- DB sayfalari --- */
        foreach (Page::query()->orderBy('slug')->get() as $s) {
            $ekle($url, route('pages.show', $s->slug), $s->updated_at);
        }

        /* --- XML --- */
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<?xml-stylesheet type="text/xsl" href="' . asset('sitemap.xsl') . '"?>' . "\n"
            . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        foreach ($url as $u) {
            $xml .= "  <url>\n";
            $xml .= '    <loc>' . htmlspecialchars($u['loc'], ENT_XML1 | ENT_QUOTES, 'UTF-8') . "</loc>\n";
            if (!empty($u['lastmod'])) $xml .= '    <lastmod>' . $u['lastmod'] . "</lastmod>\n";
            $xml .= "  </url>\n";
        }
        $xml .= '</urlset>';

        return response($xml, 200, [
            'Content-Type' => 'application/xml; charset=utf-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }


    /** Sitemap dizini: ana harita + forum parcalari. */
    public function index()
    {
        $toplam = (int) \Illuminate\Support\Facades\Cache::remember('sitemap.forum.count', 600, fn () => ForumTopic::query()->where('status', 'active')->where(fn ($q) => $q->whereNull('publish_at')->orWhere('publish_at', '<=', now()))->count());
        $sayfa = (int) ceil($toplam / self::FORUM_CHUNK);
        $loc = [route('sitemap.main')];
        for ($i = 1; $i <= $sayfa; $i++) $loc[] = route('sitemap.forum', ['page' => $i]);

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach ($loc as $l) {
            $xml .= "  <sitemap>\n    <loc>" . htmlspecialchars($l, ENT_XML1 | ENT_QUOTES, 'UTF-8') . "</loc>\n  </sitemap>\n";
        }
        $xml .= '</sitemapindex>';

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=utf-8', 'Cache-Control' => 'public, max-age=3600']);
    }

    /** Forum konulari, parca basina FORUM_CHUNK adres. */
    public function forum(int $page)
    {
        abort_if($page < 1, 404);
        $xml = \Illuminate\Support\Facades\Cache::remember('sitemap.forum.' . $page, 600, function () use ($page) {
            $konular = ForumTopic::query()->where('status', 'active')->where(fn ($q) => $q->whereNull('publish_at')->orWhere('publish_at', '<=', now()))->orderBy('id')
                ->forPage($page, self::FORUM_CHUNK)->get(['id', 'slug', 'updated_at']);
            if ($konular->isEmpty()) return null;
            $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
            foreach ($konular as $konu) {
                $xml .= "  <url>\n    <loc>" . htmlspecialchars(route('forum.show', $konu), ENT_XML1 | ENT_QUOTES, 'UTF-8') . "</loc>\n";
                if ($konu->updated_at) $xml .= '    <lastmod>' . seo_zaman($konu->updated_at) . "</lastmod>\n";
                $xml .= "  </url>\n";
            }
            return $xml . '</urlset>';
        });
        abort_if($xml === null, 404);

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=utf-8', 'Cache-Control' => 'public, max-age=3600']);
    }
}
