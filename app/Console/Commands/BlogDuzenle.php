<?php

namespace App\Console\Commands;

use App\Models\District;
use App\Models\Hotel;
use App\Models\Post;
use App\Models\Setting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Blog yazilarini Google "faydali icerik" yonergelerine gore zenginlestirir:
 *   - govdeye konuyla ilgili gorsel (alt metinli)
 *   - ic baglanti blogu (ilgili rehberler, konu bazli)
 *   - pet otel yazilarinda gercek veriye dayali Antalya modulu (ilce/hub linkleri)
 *
 * Idempotenttir: uretilen bloklar once silinip yeniden eklenir; ikinci calisma degisiklik yapmaz.
 */
class BlogDuzenle extends Command
{
    protected $signature = 'petotel:blog-duzenle
        {--id=0 : Tek yazi id}
        {--limit=0 : En fazla kac yazi (0 = hepsi)}
        {--kapsam=hepsi : hepsi|yayin|taslak}
        {--dry : Yazmadan ne degisecegini goster}
        {--ornek=0 : dry modunda ilk N yazinin yeni govdesini bas}';

    protected $description = 'Blog yazilarini zenginlestirir (govde gorseli, ic linkler, Antalya modulu).';

    public function handle(): int
    {
        $sorgu = Post::query();
        if (($id = (int) $this->option('id')) > 0) $sorgu->where('id', $id);
        $kapsam = (string) $this->option('kapsam');
        if ($kapsam === 'yayin')       $sorgu->whereNotNull('published_at');
        elseif ($kapsam === 'taslak')  $sorgu->whereNull('published_at');
        $sorgu->orderBy('id');
        if (($limit = (int) $this->option('limit')) > 0) $sorgu->limit($limit);

        $dry   = (bool) $this->option('dry');
        $ornek = (int) $this->option('ornek');
        $n = 0; $degisen = 0; $basilan = 0;

        $sorgu->chunkById(100, function ($posts) use (&$n, &$degisen, &$basilan, $dry, $ornek) {
            foreach ($posts as $p) {
                $n++;
                $yeni = $this->zenginlestir((string) $p->body, $p);
                if ($yeni === null) continue;
                $degisen++;
                if ($dry) {
                    if ($basilan < $ornek) {
                        $this->line("--- #{$p->id} {$p->slug} (uzunluk " . mb_strlen((string) $p->body) . " -> " . mb_strlen($yeni) . ") ---");
                        $this->line($yeni);
                        $basilan++;
                    }
                } else {
                    DB::table('posts')->where('id', $p->id)->update(['body' => $yeni, 'updated_at' => now()]);
                }
            }
        });

        $this->line("incelendi={$n} degisen={$degisen} dry=" . ($dry ? 'evet' : 'hayir'));
        return self::SUCCESS;
    }

    private function zenginlestir(string $govde, Post $p): ?string
    {
        $govde = str_replace(["\r\n", "\r"], "\n", $govde);
        if (trim($govde) === '') return null;
        $orijinal = $govde;

        $govde = $this->blokCikar($govde);
        $baslik = (string) $p->title;
        $slug   = (string) $p->slug;

        $govde = $this->gorselEkle($govde, $slug, $baslik);
        if ($this->antalyaMi($slug, $baslik)) $govde = $this->antalyaEkle($govde);
        $govde = $this->ilgiliEkle($govde, $p);

        $govde = trim($govde);
        return $govde === trim($orijinal) ? null : $govde;
    }

    private function blokCikar(string $govde): string
    {
        $desenler = [
            '~\s*<figure class="makale-gorsel".*?</figure>\s*~is',
            '~\s*<div class="makale-ilgili".*?</div>\s*~is',
            '~\s*<p class="makale-antalya".*?</p>\s*~is',
        ];
        foreach ($desenler as $d) $govde = preg_replace($d, '', $govde) ?? $govde;
        return $govde;
    }

    private function ilkH2(string $govde): string
    {
        if (preg_match('~<h2[^>]*>(.*?)</h2>~is', $govde, $m)) {
            return trim(preg_replace('~\s+~u', ' ', strip_tags($m[1])) ?? '');
        }
        return '';
    }

    private function gorselEkle(string $govde, string $slug, string $baslik): string
    {
        $kapakAd = pathinfo((string) parse_url(blog_cover_asset($slug, $baslik), PHP_URL_PATH), PATHINFO_FILENAME);
        $metin = $slug . ' ' . $baslik . ' ' . mb_substr(trim(strip_tags($govde)), 0, 800);
        $secim = null;
        foreach (array_keys(blog_gorsel_puanla($metin)) as $ad) {
            if ($ad === $kapakAd) continue;
            $u = blog_gorsel_url($ad);
            if ($u !== null) { $secim = $u; break; }
        }
        /* Skorlu eslesme yoksa havuzdan kapak olmayan ilk gorseli kullan (gorselsiz yazi kalmasin). */
        if ($secim === null) {
            foreach (blog_gorsel_havuzu() as $ad) {
                if ($ad === $kapakAd) continue;
                $u = blog_gorsel_url($ad);
                if ($u !== null) { $secim = $u; break; }
            }
        }
        if ($secim === null) return $govde;

        $marka = (string) Setting::al('site_name', config('app.name', 'Pet Otel'));
        $h2 = $this->ilkH2($govde);
        $h2 = preg_replace('~^\s*\d+[\.\)]\s*~u', '', $h2) ?? $h2;
        $alt = $h2 !== '' ? $h2 : $baslik;
        $blok = '<figure class="makale-gorsel">'
              . '<img src="' . $secim . '" alt="' . e($alt) . ' görseli" width="1200" height="630" loading="lazy" decoding="async">'
              . '<figcaption>' . e($baslik) . ' — ' . e($marka) . '</figcaption>'
              . '</figure>';

        /* Baslik-paragraf bitisikligini bozmamak icin ilk paragraftan SONRA ekle. */
        $pos = stripos($govde, '</p>');
        if ($pos !== false) {
            $ins = $pos + 4;
            return substr($govde, 0, $ins) . "\n" . $blok . "\n" . substr($govde, $ins);
        }
        /* Paragraf yoksa ilk h2 basligindan once ekle. */
        $pos = stripos($govde, '<h2');
        if ($pos !== false) {
            return substr($govde, 0, $pos) . $blok . "\n" . substr($govde, $pos);
        }
        return $blok . "\n" . $govde;
    }

    private function antalyaMi(string $slug, string $baslik): bool
    {
        $s = mb_strtolower($slug, 'UTF-8');
        $b = mb_strtolower($baslik, 'UTF-8');
        if (str_contains($s, 'pet-otel')) return true;
        if (str_contains($b, 'pet otel')) return true;
        if (str_contains($b, 'otel') && (str_contains($b, 'antalya') || str_contains($b, 'konaklama'))) return true;
        return false;
    }

    private function antalyaEkle(string $govde): string
    {
        $adet = Hotel::active()->count();
        $ilceler = District::query()
            ->whereIn('id', Hotel::active()->select('district_id')->distinct()->toBase())
            ->orderBy('name')->get();

        $kutu = '<p class="makale-antalya"><b>Antalya\'da pet oteli arıyorsanız:</b> listemizde '
              . $adet . ' aktif tesis bulunuyor. '
              . '<a href="' . url('/kedi-kopek-otelleri') . '">Güncel pet oteli listesi ve günlük fiyatlar</a>'
              . ' sayfasından ilçe ilçe karşılaştırabilirsiniz';
        if ($ilceler->count() > 0) {
            $parcalar = [];
            foreach ($ilceler->take(4) as $d) {
                $parcalar[] = '<a href="' . url('/' . $d->slug) . '">' . e($d->name) . '</a>';
            }
            $kutu .= '. Öne çıkan ilçeler: ' . implode(', ', $parcalar);
        }
        $kutu .= '.</p>';

        $pos = stripos($govde, '</p>');
        if ($pos !== false) {
            $ins = $pos + 4;
            return substr($govde, 0, $ins) . "\n" . $kutu . "\n" . substr($govde, $ins);
        }
        return $kutu . "\n" . $govde;
    }

    private function ilgiliEkle(string $govde, Post $p): string
    {
        $ilgili = $this->ilgili($p, 3);
        if (count($ilgili) < 2) return $govde;

        $lis = '';
        foreach ($ilgili as $y) {
            $lis .= '<li><a href="' . route('blog.show', $y->slug) . '">' . e($y->title) . '</a></li>';
        }
        $blok = '<div class="makale-ilgili"><b>İlgili rehberler</b><ul>' . $lis . '</ul></div>';

        if (preg_match('~<h2[^>]*>[^<]*(sonuç|özet)[^<]*</h2>~iu', $govde, $m, PREG_OFFSET_CAPTURE)) {
            $pos = (int) $m[0][1];
            return substr($govde, 0, $pos) . $blok . "\n" . substr($govde, $pos);
        }
        return rtrim($govde) . "\n" . $blok;
    }

    /**
     * Konu bazli ilgili yazi secimi. Ilgisiz "son yazilar" ile doldurma yapmaz;
     * yalnizca kelime veya konu ortakligi olan yazilari dondurur.
     */
    private function ilgili(Post $p, int $adet): array
    {
        $metin = mb_strtolower((string) $p->title . ' ' . (string) $p->excerpt, 'UTF-8');

        $kws = [];
        foreach (preg_split('~[^\p{L}\p{N}]+~u', mb_strtolower((string) $p->title, 'UTF-8')) as $k) {
            if (mb_strlen((string) $k) < 5) continue;
            if (in_array($k, ['arası', 'hakkında', 'nasıl', 'neden', 'kullanım', 'politikası'], true)) continue;
            $kws[] = $k;
        }
        $kws = array_slice(array_values(array_unique($kws)), 0, 8);

        $konular = [];
        foreach (['kedi', 'köpek', 'pet', 'otel', 'konaklama', 'taşıma', 'seyahat', 'bakım', 'beslenme',
                  'sağlık', 'aşı', 'parazit', 'eğitim', 'davranış', 'oyun', 'hijyen', 'rezervasyon',
                  'fiyat', 'tırmalama', 'kuduz', 'mama', 'tüy', 'kum'] as $t) {
            if (mb_strpos($metin, $t) !== false) $konular[] = $t;
        }

        $adaylar = Post::published()->where('id', '!=', $p->id)
            ->orderByDesc('published_at')->limit(150)
            ->get(['id', 'slug', 'title', 'excerpt']);

        $puanli = [];
        foreach ($adaylar as $a) {
            $bas = mb_strtolower((string) $a->title, 'UTF-8');
            $ozt = mb_strtolower((string) $a->excerpt, 'UTF-8');
            $bir = $bas . ' ' . $ozt;
            $s = 0;
            foreach ($kws as $k) {
                if (mb_strpos($bas, $k) !== false)      $s += 3;
                elseif (mb_strpos($ozt, $k) !== false)  $s += 1;
            }
            foreach ($konular as $t) {
                if (mb_strpos($bir, $t) !== false) $s += 2;
            }
            if ($s > 0) $puanli[] = ['p' => $a, 's' => $s];
        }
        usort($puanli, static fn($x, $y) => $y['s'] <=> $x['s']);

        return array_map(static fn($x) => $x['p'], array_slice($puanli, 0, $adet));
    }
}
