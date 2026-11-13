<?php

namespace App\Console\Commands;

use App\Models\Post;
use Illuminate\Console\Command;

/** Gorsel eslesme ve govde gorseli testi. */
class BlogImageProbe extends Command
{
    protected $signature = 'petotel:gorsel-test
        {--slug= : Tek bir yazi slugi}
        {--n=12 : Kac yazi gosterilsin}';

    protected $description = 'Blog gorsel eslesmesini test eder';

    public function handle(): int
    {
        $slug = (string) $this->option('slug');
        if ($slug !== '') {
            $p = Post::where('slug', $slug)->first();
            $baslik = $p?->title ?? '';
            $this->line("baslik : {$baslik}");
            $this->kapak($slug, $baslik);
            return self::SUCCESS;
        }

        $posts = Post::orderByDesc('id')->limit(max(1, (int) $this->option('n')))
            ->get(['slug', 'title']);
        foreach ($posts as $p) {
            $this->kapak((string) $p->slug, (string) $p->title);
        }
        return self::SUCCESS;
    }

    private function kapak(string $slug, string $baslik): void
    {
        $url = blog_cover_asset($slug, $baslik);
        $puan = blog_gorsel_puanla($slug . ' ' . $baslik);
        $ilk = array_slice(array_keys($puan), 0, 3);
        $this->line(sprintf(
            "%-38s => %-34s | %s",
            mb_substr($slug, 0, 38),
            basename((string) $url),
            implode(', ', array_map(fn($a) => $a . '(' . $puan[$a] . ')', $ilk))
        ));
    }
}
