<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * Publishes queued drafts when their scheduled timestamp has arrived.
 *
 * Pre-publish quality gate (Google "helpful content" uyumu):
 *  - yeterli uzunluk, yapi (en az 2 H2), ozet, baslik uzunlugu
 *  - govdede en az 1 ic link ve en az 1 gorsel
 *  - yayindaki veya daha once kuyruga alinmis yazilarla yakin-kopya olmama
 * Kapiyi gecemeyen taslaklar yayinlanmaz; --ertele ile ileri bir tarihe otele­nir.
 */
class PublishDraftPosts extends Command
{
    protected $signature = "petotel:blog-yayinla
        {--gunluk=20 : Daily maximum, above the normal scheduled volume}
        {--aralik=30 : Minimum minutes between catch-up publications (scheduled posts are not delayed)}
        {--tolerans=15 : Minutes after the scheduled time a post still counts as on-time}
        {--min-uzunluk=2200 : Minimum body text length in characters}
        {--baslangic=8 : Normal publication window start hour}
        {--bitis=22 : Normal publication window end hour}
        {--denetle : Audit all queued drafts against the quality gate (no publishing)}
        {--ertele : Defer drafts that fail the quality gate by pushing their scheduled date}
        {--ertele-gun=180 : Days to push back failing drafts when --ertele is used}
        {--listele=30 : How many failing drafts to list in audit mode}
        {--dry : Show the next due draft without publishing}";

    protected $description = "Publish due blog drafts in scheduled order (quality-gated)";

    private const TITLE_STOP = [
        'ile', 'icin', 'nasil', 'neden', 'nedir', 'hangi', 'daha', 'bir', 've', 'ama', 'veya',
        'gibi', 'kadar', 'sonra', 'once', 'olarak', 'olan', 'temel', 'genel', 'rehberi', 'rehber',
        '2026', 'pet',
    ];

    private int $minUzunluk = 2200;

    public function handle(): int
    {
        $this->minUzunluk = max(500, (int) $this->option("min-uzunluk"));

        if ($this->option("denetle")) {
            return $this->denetle((bool) $this->option("ertele"), max(1, (int) $this->option("ertele-gun")));
        }

        $now = now();
        $dailyLimit = max(0, (int) $this->option("gunluk"));
        $minimumGap = max(0, (int) $this->option("aralik"));
        $tolerance = max(5, (int) $this->option("tolerans"));
        $erteleGun = max(1, (int) $this->option("ertele-gun"));

        if ($dailyLimit === 0) {
            return $this->report("daily limit is 0");
        }

        $columns = ["id", "title", "slug", "created_at", "updated_at", "body", "excerpt"];

        // 1) ON-TIME: zamani yeni gelmis yazi, gunluk limitten ve bekleme araligindan bagimsiz yayinlanir.
        //    Boylece gece biriken gecikmeler gunun kalan planli saatlerini bloke etmez.
        $onTimeFrom = $now->copy()->subMinutes($tolerance);
        $draft = DB::table("posts")->whereNull("published_at")
            ->where("created_at", "<=", $now)
            ->where("created_at", ">=", $onTimeFrom)
            ->orderBy("created_at")->orderBy("id")
            ->first($columns);
        $mode = $draft ? "on-time" : null;

        if (!$draft) {
            // 2) CATCH-UP: gecikmis kuyruk. Bugunun kalan planli yazilarina yer birakilir.
            $publishedToday = DB::table("posts")->whereNotNull("published_at")
                ->where("published_at", ">=", $now->copy()->startOfDay())
                ->where("published_at", "<=", $now)->count();

            $remainingToday = DB::table("posts")->whereNull("published_at")
                ->whereDate("created_at", $now->toDateString())
                ->where("created_at", ">", $now)->count();

            if ($publishedToday + $remainingToday >= $dailyLimit) {
                return $this->report("catch-up quota full (published today: {$publishedToday}, reserved for today's slots: {$remainingToday}, daily: {$dailyLimit})");
            }

            if ($minimumGap > 0) {
                $lastPublishedAt = DB::table("posts")->whereNotNull("published_at")
                    ->where("published_at", "<=", $now)->max("published_at");
                if ($lastPublishedAt && ($now->timestamp - strtotime($lastPublishedAt)) < $minimumGap * 60) {
                    return $this->report("catch-up spacing: last publish {$lastPublishedAt}, need {$minimumGap} min");
                }
            }

            $draft = DB::table("posts")->whereNull("published_at")
                ->where("created_at", "<", $onTimeFrom)
                ->whereRaw("CHAR_LENGTH(body) >= ?", [$this->minUzunluk])
                ->where("body", "like", "%<h2%")
                ->whereNotNull("excerpt")->where("excerpt", "<>", "")
                ->orderBy("created_at")->orderBy("id")
                ->first($columns);
            $mode = $draft ? "catch-up" : null;
        }

        if (!$draft) {
            return $this->report("no eligible draft is due yet");
        }

        // ---- Kalite kapisi ----
        $sebepler = $this->kapi($draft);

        if (!$sebepler) {
            $kopya = $this->yakinKopyaVar($this->imza($draft), $this->yayindakiler());
            if ($kopya !== null) {
                $sebepler[] = "yakin-kopya:{$kopya}";
            }
        }

        if ($sebepler) {
            $yeniTarih = Carbon::parse((string) $draft->created_at)->addDays($erteleGun);
            DB::table("posts")->where("id", $draft->id)->whereNull("published_at")
                ->update(["created_at" => $yeniTarih, "updated_at" => now()]);

            return $this->report("[kalite-kapisi] #{$draft->id} {$draft->slug} -> ertelendi ({$yeniTarih->toDateString()}): " . implode(", ", $sebepler));
        }

        if ($this->option("dry")) {
            return $this->report("[dry-run] next due ({$mode}): #{$draft->id} {$draft->slug} (scheduled {$draft->created_at}) [kapi: GECTI]");
        }

        $published = DB::table("posts")->where("id", $draft->id)->whereNull("published_at")
            ->where("created_at", "<=", $now)
            ->update(["published_at" => $now, "updated_at" => $now]);

        return $this->report($published
            ? "published ({$mode}): #{$draft->id} {$draft->slug} (scheduled {$draft->created_at})"
            : "draft changed concurrently; skipped");
    }

    // ------------------------------------------------------------------
    //  Denetim: tum taslaklari sirayla kapidan gecir, isteyene otele
    // ------------------------------------------------------------------
    private function denetle(bool $ertele, int $erteleGun): int
    {
        $pub = $this->yayindakiler();
        $kabul = [];            // kapidan gecen taslaklarin imzalari
        $sebepSayac = [];
        $hatali = [];
        $toplam = 0;
        $gecen = 0;
        $ertelenen = 0;
        $zatenErteli = 0;
        $sinir = now()->addDays(100);   // kuyruk zaten bu tarihin otesine uzaniyor
        $idler = DB::table("posts")->whereNull("published_at")
            ->orderBy("created_at")->orderBy("id")->pluck("id")->all();

        // Sabit ID listesi uzerinden ilerle: erteleme sirasinda offset kaymasi olmaz.
        foreach (array_chunk($idler, 200) as $grup) {
            $taslaklar = DB::table("posts")->whereIn("id", $grup)
                ->orderBy("created_at")->orderBy("id")
                ->select(["id", "title", "slug", "created_at", "body", "excerpt"])->get();
            foreach ($taslaklar as $p) {
                $toplam++;
                $sebepler = $this->kapi($p);
                $imza = $this->imza($p);
                if (!$sebepler) {
                    $kopya = $this->yakinKopyaVar($imza, $pub) ?? $this->yakinKopyaVar($imza, $kabul);
                    if ($kopya !== null) {
                        $sebepler[] = "yakin-kopya:{$kopya}";
                    }
                }

                if ($sebepler) {
                    // Daha once bilahere ertelenmis (kuyruk disi) yazilari tekrar oteleme.
                    if (Carbon::parse((string) $p->created_at)->greaterThan($sinir)) {
                        $zatenErteli++;
                        continue;
                    }
                    $ertelenen++;
                    foreach ($sebepler as $s) {
                        $anahtar = str_starts_with($s, "yakin-kopya") ? "yakin-kopya" : $s;
                        $sebepSayac[$anahtar] = ($sebepSayac[$anahtar] ?? 0) + 1;
                    }
                    if (count($hatali) < max(0, (int) $this->option("listele"))) {
                        $hatali[] = "#{$p->id} " . mb_substr((string) $p->title, 0, 52) . " [" . implode(",", $sebepler) . "]";
                    }
                    if ($ertele) {
                        DB::table("posts")->where("id", $p->id)->whereNull("published_at")
                            ->update([
                                "created_at" => Carbon::parse((string) $p->created_at)->addDays($erteleGun),
                                "updated_at" => now(),
                            ]);
                    }
                    continue;
                }

                $gecen++;
                $kabul[] = $imza;
            }
        }

        $this->line("=== blog kalite denetimi ===");
        $this->line("taslak: {$toplam} | kapi GECEN: {$gecen} | ERTELENEN/ELE: {$ertelenen}" . ($ertele ? " (erteleme uygulandi +{$erteleGun} gun)" : " (inceleme modu)") . ($zatenErteli ? " | zaten-ertelenmis: {$zatenErteli}" : ""));
        if ($sebepSayac) {
            arsort($sebepSayac);
            $this->line("sebep dagilimi: " . json_encode($sebepSayac, JSON_UNESCAPED_UNICODE));
        }
        if ($hatali) {
            $this->line("--- ilk hatalilar ---");
            foreach ($hatali as $h) {
                $this->line("  " . $h);
            }
        }

        return self::SUCCESS;
    }

    // ------------------------------------------------------------------
    //  Kalite kapisi
    // ------------------------------------------------------------------
    private function kapi(stdClass $p): array
    {
        $sebep = [];
        $body = (string) $p->body;
        $metin = mb_strlen(trim(strip_tags($body)));

        if ($metin < $this->minUzunluk) {
            $sebep[] = "kisa({$metin})";
        }
        if (substr_count($body, "<h2") < 2) {
            $sebep[] = "yapi(az-h2)";
        }
        if (mb_strlen(trim(strip_tags((string) $p->excerpt))) < 80) {
            $sebep[] = "zayif-ozet";
        }
        $baslikUzunluk = mb_strlen(trim((string) $p->title));
        if ($baslikUzunluk < 25 || $baslikUzunluk > 100) {
            $sebep[] = "baslik({$baslikUzunluk})";
        }
        if (!preg_match('~href="(https://antalyapetotel\.com/|/)~u', $body)) {
            $sebep[] = "ic-link-yok";
        }
        if (!preg_match('~<img\b~iu', $body)) {
            $sebep[] = "gorsel-yok";
        }

        return $sebep;
    }

    // ------------------------------------------------------------------
    //  Yakin-kopya korumasi (tur notrallestirilmis 3-gram Jaccard)
    // ------------------------------------------------------------------
    private function yayindakiler(): array
    {
        $liste = [];
        DB::table("posts")->whereNotNull("published_at")
            ->select(["id", "title", "body"])->orderBy("id")
            ->chunk(200, function ($ps) use (&$liste) {
                foreach ($ps as $p) {
                    $liste[] = $this->imza($p);
                }
            });

        return $liste;
    }

    private function imza(stdClass $p): array
    {
        return [
            "id" => (int) $p->id,
            "baslik" => $this->baslikKelimeleri((string) $p->title),
            "govde" => $this->govdeShingle((string) $p->body),
        ];
    }

    private function yakinKopyaVar(array $imza, array $liste): ?int
    {
        foreach ($liste as $x) {
            if ($this->jaccard($imza["baslik"], $x["baslik"]) < 0.6) {
                continue;
            }
            if ($this->jaccard($imza["govde"], $x["govde"]) >= 0.45) {
                return $x["id"];
            }
        }

        return null;
    }

    private function baslikKelimeleri(string $baslik): array
    {
        $s = $this->sadelestir($baslik);
        $kelimeler = array_filter(
            explode(" ", $s),
            static fn ($k) => mb_strlen($k) >= 4 && !in_array($k, self::TITLE_STOP, true)
        );

        return array_values(array_unique($kelimeler));
    }

    private function govdeShingle(string $body): array
    {
        $metin = $this->sadelestir(mb_substr(strip_tags($body), 0, 2500));
        $kelimeler = explode(" ", $metin);
        $adet = count($kelimeler);
        $set = [];
        $limit = 600;
        for ($i = 0; $i + 3 <= $adet; $i++) {
            $set[$kelimeler[$i] . " " . $kelimeler[$i + 1] . " " . $kelimeler[$i + 2]] = 1;
            if (count($set) >= $limit) {
                break;
            }
        }

        return array_keys($set);
    }

    /** Kucuk harf + TR karakter sadelestirme + tur adlarini notrleme. */
    private function sadelestir(string $s): string
    {
        $s = mb_strtolower($s, "UTF-8");
        $s = strtr($s, [
            "ı" => "i", "İ" => "i", "ş" => "s", "Ş" => "s", "ğ" => "g", "Ğ" => "g",
            "ü" => "u", "Ü" => "u", "ö" => "o", "Ö" => "o", "ç" => "c", "Ç" => "c",
            "â" => "a", "î" => "i", "û" => "u",
        ]);
        $s = preg_replace('~[^a-z0-9]+~', " ", $s) ?? "";
        $kelimeler = explode(" ", trim($s));
        $out = [];
        foreach ($kelimeler as $k) {
            if ($k === "") {
                continue;
            }
            if (preg_match('~^(kedi|kopek)~', $k)) {
                $out[] = "pet";
                continue;
            }
            $out[] = $k;
        }

        return implode(" ", $out);
    }

    private function jaccard(array $a, array $b): float
    {
        if (!$a || !$b) {
            return 0.0;
        }
        $set = array_flip($b);
        $kesisim = 0;
        foreach ($a as $k) {
            if (isset($set[$k])) {
                $kesisim++;
            }
        }
        $birlesim = count($a) + count($b) - $kesisim;

        return $birlesim > 0 ? $kesisim / $birlesim : 0.0;
    }

    private function report(string $message): int
    {
        $this->line("[" . now()->format("Y-m-d H:i:s") . "] blog_publish: " . $message);
        return self::SUCCESS;
    }
}