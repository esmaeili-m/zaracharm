<?php

namespace App\Services\Media;

use Illuminate\Http\UploadedFile;
use Throwable;

/**
 * فشرده‌سازی تصویر با GD (تنظیمات: config/media.php)
 *
 * - اصلاح چرخش عکس‌های موبایل (EXIF)
 * - کوچک کردن تا حداکثر ابعاد با حفظ نسبت
 * - ذخیره با کیفیت تنظیم‌شده (پیش‌فرض WebP؛ شفافیت PNG حفظ می‌شود)
 *
 * خروجی null یعنی فایل اصلی بدون تغییر ذخیره شود (فرمت پشتیبانی‌نشده، GD نصب نیست، نتیجه بزرگ‌تر شد، ...).
 */
class ImageCompressor
{
    protected const SUPPORTED = ['image/jpeg', 'image/png', 'image/webp', 'image/bmp', 'image/x-ms-bmp'];

    /**
     * @return array{path: string, mime: string, extension: string, size: int, width: int, height: int}|null
     */
    public function compress(UploadedFile $file): ?array
    {
        if (! config('media.compress_images', true) || ! extension_loaded('gd')) {
            return null;
        }

        $mime = (string) $file->getMimeType();

        if (! in_array($mime, self::SUPPORTED, true)) {
            return null;
        }

        $source = $file->getRealPath();
        $info = @getimagesize($source);

        if (! $info) {
            return null;
        }

        [$width, $height] = $info;
        $maxW = (int) config('media.max_width', 1920);
        $maxH = (int) config('media.max_height', 1920);
        $orientation = $this->orientation($source, $mime);
        $rotated = in_array($orientation, [5, 6, 7, 8], true);

        // ابعاد پس از اصلاح چرخش
        [$realW, $realH] = $rotated ? [$height, $width] : [$width, $height];
        $needsResize = $realW > $maxW || $realH > $maxH;

        if (! $needsResize && $orientation <= 1 && $file->getSize() < (int) config('media.skip_below_kb', 60) * 1024) {
            return null;
        }

        // جلوگیری از پر شدن حافظه با تصاویر بسیار بزرگ
        if ($width * $height > 50_000_000) {
            return null;
        }

        try {
            $image = match ($mime) {
                'image/jpeg' => @imagecreatefromjpeg($source),
                'image/png' => @imagecreatefrompng($source),
                'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($source) : false,
                default => function_exists('imagecreatefrombmp') ? @imagecreatefrombmp($source) : false,
            };

            if (! $image) {
                return null; // مثلاً WebP متحرک
            }

            $image = $this->applyOrientation($image, $orientation);
            $image = $this->resize($image, $maxW, $maxH);

            [$format, $outMime, $extension] = $this->outputFormat($mime);
            $target = tempnam(sys_get_temp_dir(), 'img');
            $quality = max(1, min(100, (int) config('media.quality', 80)));

            $ok = match ($format) {
                'webp' => imagewebp($image, $target, $quality),
                'jpeg' => imagejpeg($this->flatten($image), $target, $quality),
                'png' => imagepng($image, $target, 9),
            };

            $outW = imagesx($image);
            $outH = imagesy($image);
            imagedestroy($image);

            clearstatcache(true, $target);
            $size = $ok ? (int) filesize($target) : 0;

            // نتیجه بزرگ‌تر از اصل (و بدون نیاز به تغییر ابعاد/چرخش) => همان فایل اصلی
            if (! $ok || $size === 0 || ($size >= $file->getSize() && ! $needsResize && $orientation <= 1)) {
                @unlink($target);

                return null;
            }

            return ['path' => $target, 'mime' => $outMime, 'extension' => $extension, 'size' => $size, 'width' => $outW, 'height' => $outH];
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }

    /** @return array{0: string, 1: string, 2: string} [format, mime, extension] */
    protected function outputFormat(string $mime): array
    {
        $format = config('media.format', 'webp');

        if ($format === 'webp' && function_exists('imagewebp')) {
            return ['webp', 'image/webp', 'webp'];
        }

        if ($format === 'jpeg' || $mime === 'image/jpeg' || $mime === 'image/bmp' || $mime === 'image/x-ms-bmp') {
            return ['jpeg', 'image/jpeg', 'jpg'];
        }

        if ($mime === 'image/webp' && function_exists('imagewebp')) {
            return ['webp', 'image/webp', 'webp'];
        }

        return ['png', 'image/png', 'png'];
    }

    protected function orientation(string $path, string $mime): int
    {
        if ($mime !== 'image/jpeg' || ! function_exists('exif_read_data')) {
            return 1;
        }

        $exif = @exif_read_data($path);

        return (int) ($exif['Orientation'] ?? 1);
    }

    /** @param \GdImage $image */
    protected function applyOrientation($image, int $orientation)
    {
        if ($orientation <= 1) {
            return $image;
        }

        if (in_array($orientation, [2, 4, 5, 7], true)) {
            imageflip($image, $orientation === 4 ? IMG_FLIP_VERTICAL : IMG_FLIP_HORIZONTAL);
        }

        $angle = match ($orientation) {
            3, 4 => 180,
            5, 6 => -90,
            7, 8 => 90,
            default => 0,
        };

        if ($angle !== 0) {
            $rotated = imagerotate($image, $angle, 0);
            imagedestroy($image);
            $image = $rotated;
        }

        return $image;
    }

    /** @param \GdImage $image */
    protected function resize($image, int $maxW, int $maxH)
    {
        $w = imagesx($image);
        $h = imagesy($image);
        $ratio = min($maxW / $w, $maxH / $h, 1);

        if ($ratio >= 1) {
            imagesavealpha($image, true);

            return $image;
        }

        $newW = max(1, (int) round($w * $ratio));
        $newH = max(1, (int) round($h * $ratio));

        $canvas = imagecreatetruecolor($newW, $newH);
        $this->preserveAlpha($canvas);
        imagecopyresampled($canvas, $image, 0, 0, 0, 0, $newW, $newH, $w, $h);
        imagedestroy($image);

        return $canvas;
    }

    /** @param \GdImage $image */
    protected function preserveAlpha($image): void
    {
        imagealphablending($image, false);
        imagesavealpha($image, true);
        $transparent = imagecolorallocatealpha($image, 0, 0, 0, 127);
        imagefill($image, 0, 0, $transparent);
    }

    /** JPEG شفافیت ندارد: پس‌زمینه سفید */
    protected function flatten($image)
    {
        $w = imagesx($image);
        $h = imagesy($image);
        $canvas = imagecreatetruecolor($w, $h);
        imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
        imagealphablending($canvas, true);
        imagecopy($canvas, $image, 0, 0, 0, 0, $w, $h);

        return $canvas;
    }
}
