<?php
namespace App\Http\Controllers;

use App\Models\District;
use App\Models\Hotel;
use App\Models\Post;

class BlogController extends Controller
{
    public function index()
    {
        $tur = (string) request('tur', '');
        $sorgu = Post::published();

        if ($tur === 'kedi')       $sorgu->where('title', 'like', '%kedi%');
        if ($tur === 'kopek')      $sorgu->where('title', 'like', '%köpek%');
        if ($tur === 'otel')       $sorgu->where('title', 'like', '%otel%');

        $posts = $sorgu->latest('published_at')->paginate(9);

        /* Konu kumeleri: her kume, sayfadaki yazilardan GERCEKTEN
         * eslesenleri listeler. Bilesik kelime eslesmesi kullanilir
         * ki "kedili" yazisi "kedi" kumesine yanlis girmez. */
        $hepsi = Post::published()
            ->orderByDesc('published_at')->limit(80)
            ->get(['slug', 'title', 'excerpt', 'body', 'published_at']);

        $konular = [];
        foreach ([
            'kedi'        => ['Kedi oteli ve kedi konaklaması', ['kedi', 'kediler']],
            'kopek'       => ['Köpek oteli ve köpek konaklaması', ['köpek', 'köpekler', 'it']],
            'oda'         => ['Oda seçimi, büyüklük ve fiyat', ['oda', 'fiyat', 'ücret']],
            'giris'       => ['Giriş, çıkış ve günlük program', ['giriş', 'çıkış', 'günlük']],
            'saglik'      => ['Aşı, parazit ve sağlık kaydı', ['aşı', 'parazit', 'sağlık', 'veteriner']],
            'rezervasyon' => ['Rezervasyon, iptal ve ödeme', ['rezervasyon', 'iptal', 'ödeme']],
        ] as $anahtar => [$ad, $kelimeler]) {
            $liste = $hepsi->filter(function ($p) use ($kelimeler) {
                $m = mb_strtolower((string) $p->title . ' ' . (string) $p->excerpt);
                foreach ($kelimeler as $k) if (str_contains($m, $k)) return true;
                return false;
            })->take(5)->values();
            $konular[] = ['ad' => $ad, 'yazilar' => $liste];
        }

        return view('blog.index', [
            'posts' => $posts,
            'tur'   => $tur,
            'konular' => $konular,            'kategoriler' => [
                ''       => 'Tümü',
                'kedi'   => 'Kedi',
                'kopek'  => 'Köpek',
                'otel'   => 'Pet Otel',
            ],
            'ilceler' => District::query()
                ->whereIn('id', Hotel::active()->select('district_id')->distinct()->toBase())
                ->orderBy('name')->get(),
        ]);
    }

    public function show(string $slug)
    {
        $post = Post::published()->where('slug', $slug)->firstOrFail();

        /* Ilgili yazilar: ayni konu kumesinden sec.
         * Anahtar kelimeler baslik + ozetten turetilir; boylece
         * "kopek oteli egitimi" yazisi kedi yazisiyla eslesmez. */
        $yazilar = $this->ilgili($post);

        /* Onceki / sonraki (yayin tarihine gore) */
        $onceki = Post::published()->where('published_at', '<', $post->published_at)
            ->orderByDesc('published_at')->first(['slug', 'title', 'published_at']);
        $sonraki = Post::published()->where('published_at', '>', $post->published_at)
            ->orderBy('published_at')->first(['slug', 'title', 'published_at']);

        /* Blogdan otele ic baglanti: yazida gecen ilce adlari */
        $ilceler = District::query()
            ->whereIn('id', Hotel::active()->select('district_id')->distinct()->toBase())
            ->orderBy('name')->get()
            ->filter(fn($d) => $d->name !== ''
                && mb_stripos((string) $post->title . ' ' . (string) $post->excerpt
                     . ' ' . strip_tags((string) $post->body), $d->name) !== false)
            ->values();

        return view('blog.show', [
            'post'    => $post,
            'yazilar' => $yazilar,
            'onceki'  => $onceki,
            'sonraki' => $sonraki,
            'ilceler' => $ilceler,
        ]);
    }

    /**
     * Konu eslesmeli ilgili yazi secimi.
     * Girdi: mevcut yazi. Cikti: en fazla 6 yazi (dizi).
     */
    private function ilgili(Post $mevcut): array
    {
        $metin = mb_strtolower(
            (string) $mevcut->title . ' ' . (string) $mevcut->excerpt . ' '
            . mb_substr(strip_tags((string) $mevcut->body), 0, 1500));

        /* Yazi basligindaki anlamli kelimeler */
        $kelime = [];
        foreach (preg_split('~[^\p{L}\p{N}]+~u', mb_strtolower((string) $mevcut->title)) as $k) {
            if (mb_strlen($k) < 5) continue;
            if (in_array($k, ['arası', 'hakkında', 'nasıl', 'neden', 'kullanım', 'politikası'], true)) continue;
            $kelime[] = $k;
        }
        $kelime = array_slice(array_values(array_unique($kelime)), 0, 8);

        $adaylar = Post::published()
            ->where('id', '!=', $mevcut->id)
            ->orderByDesc('published_at')
            ->limit(80)
            ->get(['id', 'slug', 'title', 'excerpt', 'published_at']);

        $puanli = [];
        foreach ($adaylar as $p) {
            $baslik = mb_strtolower((string) $p->title);
            $puan = 0;
            foreach ($kelime as $k) {
                if (mb_strpos($baslik, $k) !== false)      $puan += 3;  /* baslikta gecis */
                elseif (mb_strpos(mb_strtolower((string) $p->excerpt), $k) !== false) $puan += 1;
            }
            /* Ayni konu grubu (kedi/kopek/otel) kucuk agirlik */
            foreach (['kedi', 'köpek', 'otel', 'konaklama', 'rezervasyon'] as $konu) {
                if (str_contains($metin, $konu) && str_contains($baslik, $konu)) $puan += 1;
            }
            if ($puan > 0) $puanli[] = ['p' => $p, 's' => $puan];
        }
        usort($puanli, static fn($a, $b) => $b['s'] <=> $a['s']);

        return array_slice(array_column(array_slice($puanli, 0, 6), 'p'), 0, 6);
    }
}