<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\ImageHelper;
use Illuminate\Console\Command;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

class GenerateWebpVariants extends Command
{
    protected $signature = 'images:webp-variants {--limit=500} {--quality=82}';

    protected $description = 'Yüklenen JPEG/PNG kapak görselleri için WebP türevleri üretir';

    public function handle(): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $quality = max(40, min(95, (int) $this->option('quality')));
        $roots = array_filter([
            storage_path('app/public'),
            public_path('img/otel'),
        ], fn (string $path) => is_dir($path));

        $created = 0;
        $scanned = 0;

        foreach ($roots as $root) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                if (! $file instanceof SplFileInfo || ! $file->isFile()) {
                    continue;
                }

                if (! preg_match('~\.(jpe?g|png)$~i', $file->getFilename())) {
                    continue;
                }

                $scanned++;

                if (ImageHelper::webpVariant($file->getPathname(), $quality)) {
                    $created++;
                }

                if ($created >= $limit) {
                    break 2;
                }
            }
        }

        $this->info("taranan: {$scanned} · webp_uretilen: {$created}");

        return self::SUCCESS;
    }
}
