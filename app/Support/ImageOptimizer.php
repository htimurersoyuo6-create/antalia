<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** All accepted uploaded photos are stored as WebP. */
final class ImageOptimizer
{
    public const MAX_KENAR = 2000;
    public const MAX_PIKSEL = 24000000;

    /** @return array{0:string,1:string}|null [WebP bytes, extension] */
    public static function optimize(string $path, int $maxKenar = self::MAX_KENAR): ?array
    {
        if (!extension_loaded('gd') || !function_exists('imagewebp') || !is_file($path)) return null;
        $info = @getimagesize($path);
        if (!$info) return null;
        [$width, $height, $type] = $info;
        if ($width < 1 || $height < 1 || $width * $height > self::MAX_PIKSEL) return null;
        $source = match ($type) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($path),
            IMAGETYPE_PNG => @imagecreatefrompng($path),
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false,
            IMAGETYPE_GIF => function_exists('imagecreatefromgif') ? @imagecreatefromgif($path) : false,
            default => false,
        };
        if (!$source) return null;
        if ($type === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
            $exif = @exif_read_data($path);
            $angle = match ((int) ($exif['Orientation'] ?? 1)) { 3 => 180, 6 => -90, 8 => 90, default => 0 };
            if ($angle !== 0) {
                $rotated = @imagerotate($source, $angle, 0);
                if ($rotated) { $source = $rotated; }
            }
        }
        $width = imagesx($source); $height = imagesy($source);
        $scale = min(1.0, $maxKenar / max($width, $height));
        $targetWidth = max(1, (int) round($width * $scale));
        $targetHeight = max(1, (int) round($height * $scale));
        $target = imagecreatetruecolor($targetWidth, $targetHeight);
        imagealphablending($target, false); imagesavealpha($target, true);
        imagefill($target, 0, 0, imagecolorallocatealpha($target, 255, 255, 255, 127));
        imagecopyresampled($target, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);
        ob_start(); $ok = imagewebp($target, null, 84); $bytes = (string) ob_get_clean();
        return $ok && $bytes !== '' ? [$bytes, 'webp'] : null;
    }

    public static function storeUploaded(UploadedFile $file, string $disk, string $directory, string $field = 'image'): string
    {
        $optimized = self::optimize((string) $file->getRealPath());
        if (!$optimized) throw ValidationException::withMessages([$field => 'Fotoğraf WebP biçimine dönüştürülemedi. JPG, PNG, GIF veya WebP görseli deneyin.']);
        $path = trim($directory, '/') . '/' . Str::random(40) . '.webp';
        if (!Storage::disk($disk)->put($path, $optimized[0])) throw ValidationException::withMessages([$field => 'WebP fotoğraf sunucuya kaydedilemedi. Lütfen tekrar deneyin.']);
        return $path;
    }
}
