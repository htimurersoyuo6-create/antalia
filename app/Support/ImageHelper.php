<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Log;
use Throwable;

class ImageHelper
{
    public static function cdn(?string $path): string
    {
        $path = ltrim((string) $path, '/');
        $base = rtrim((string) config('services.cdn.base_url', ''), '/');

        if ($base !== '' && $path !== '') {
            return $base.'/'.$path;
        }

        return asset($path);
    }

    public static function webpVariant(string $absolutePath, int $quality = 82): ?string
    {
        if (! is_file($absolutePath)) {
            return null;
        }

        $info = @getimagesize($absolutePath);

        if (! $info || ! in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG], true)) {
            return null;
        }

        $target = preg_replace('~\.(jpe?g|png)$~i', '.webp', $absolutePath);

        if ($target === $absolutePath) {
            return null;
        }

        if (is_file($target) && filemtime($target) >= filemtime($absolutePath)) {
            return $target;
        }

        try {
            $image = $info[2] === IMAGETYPE_JPEG
                ? @imagecreatefromjpeg($absolutePath)
                : @imagecreatefrompng($absolutePath);

            if (! $image) {
                return null;
            }

            if ($info[2] === IMAGETYPE_PNG) {
                imagepalettetotruecolor($image);
                imagealphablending($image, true);
                imagesavealpha($image, true);
            }

            imagewebp($image, $target, max(40, min(95, $quality)));

            return is_file($target) ? $target : null;
        } catch (Throwable $e) {
            Log::warning('image.webp_failed', ['file' => $absolutePath, 'message' => $e->getMessage()]);

            return null;
        }
    }

    public static function publicWebp(string $relative): ?string
    {
        $relative = ltrim($relative, '/');
        $absolute = public_path($relative);
        $generated = self::webpVariant($absolute);

        if (! $generated) {
            return null;
        }

        $webpRelative = preg_replace('~\.(jpe?g|png)$~i', '.webp', $relative);

        return $webpRelative ? self::cdn($webpRelative) : null;
    }
}
