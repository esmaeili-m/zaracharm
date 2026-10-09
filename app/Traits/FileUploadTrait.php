<?php

namespace App\Traits;

use App\Models\Media;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
trait FileUploadTrait
{
    public function upload(
        UploadedFile $file,
        Model $model,
        string $collection,
        string $disk = 'public',
        $purpose='main',
        $sort=1,
        $name=null,
        $meta=[],
        bool $compress = true
    ): ?Media
    {
        Validator::make(
            ['file' => $file],
            ['file' => 'required|file|max:512000']
        )->validate();

        $this->rejectExecutableUpload($file);

        // فشرده‌سازی خودکار تصاویر (config/media.php)؛ در صورت عدم امکان، فایل اصلی ذخیره می‌شود
        $compressed = $compress ? app(\App\Services\Media\ImageCompressor::class)->compress($file) : null;

        if ($compressed) {
            $path = Storage::disk($disk)->putFileAs(
                $collection,
                new \Illuminate\Http\File($compressed['path']),
                Str::random(40) . '.' . $compressed['extension']
            );
            @unlink($compressed['path']);

            $meta = array_merge((array) $meta, [
                'compressed' => true,
                'original_size' => $file->getSize(),
                'width' => $compressed['width'],
                'height' => $compressed['height'],
            ]);
        } else {
            $path = $file->store($collection, $disk);
        }

        if (!$path) return null;

        $mime = $compressed['mime'] ?? $file->getMimeType();
        $extension = $compressed['extension'] ?? strtolower($file->getClientOriginalExtension());

        $type = match (true) {

            // Images
            str_contains($mime, 'image') => 'image',

            // Videos
            str_contains($mime, 'video') => 'video',

            // Audio
            str_contains($mime, 'audio') => 'audio',

            // PDF
            $mime === 'application/pdf' => 'document',

            // Word
            in_array($mime, [
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            ]) => 'document',

            // Excel
            in_array($mime, [
                'application/vnd.ms-excel',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ]) => 'spreadsheet',

            // PowerPoint
            in_array($mime, [
                'application/vnd.ms-powerpoint',
                'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            ]) => 'presentation',

            // ZIP / archives
            in_array($mime, [
                'application/zip',
                'application/x-rar-compressed',
                'application/x-7z-compressed',
            ]) => 'archive',

            str_contains($mime, 'text/') => 'text',
            default => match ($extension) {
                'jpg', 'jpeg', 'png', 'gif', 'webp' => 'image',
                'mp4', 'mov', 'avi', 'mkv' => 'video',
                'mp3', 'wav', 'ogg' => 'audio',
                'pdf' => 'document',
                'doc', 'docx' => 'document',
                'xls', 'xlsx' => 'spreadsheet',
                'ppt', 'pptx' => 'presentation',
                'zip', 'rar', '7z' => 'archive',
                'txt', 'md' => 'text',
                default => 'other',
            }
        };

        return Media::create([
        'uuid' => Str::uuid(),

        'name' => $name ?? $file->getClientOriginalName(),

        'mediable_id' => $model->id,

        'mediable_type' => get_class($model),

        'file_path' => $path,

        'disk' => $disk,

        'collection' => $collection,

        'mime_type' => $mime,

        'extension' => $extension,

        'size' => $compressed['size'] ?? $file->getSize(),

        'type' => $type,

        'is_private' => false,

        'purpose' => $purpose,

        'sort' => $sort,

        'meta' => $meta
    ]);
    }
    /**
     * لایه دفاعی مشترک: فایل‌های قابل اجرا / اسکریپت هرگز در دیسک عمومی ذخیره نشوند
     * (store() پسوند را از MIME حدس می‌زند؛ یک فایل PHP با پسوند .php در public/storage قرار می‌گرفت)
     */
    protected function rejectExecutableUpload(UploadedFile $file): void
    {
        $blocked = [
            'php', 'php3', 'php4', 'php5', 'php7', 'php8', 'phtml', 'pht', 'phps', 'phar', 'inc',
            'cgi', 'pl', 'py', 'rb', 'sh', 'bash', 'asp', 'aspx', 'jsp', 'exe', 'bat', 'cmd', 'com', 'msi', 'dll',
            'html', 'htm', 'xhtml', 'shtml', 'js', 'mjs', 'htaccess', 'htpasswd',
        ];

        $extensions = array_filter([
            strtolower((string) $file->getClientOriginalExtension()),
            strtolower((string) $file->guessExtension()),
        ]);

        // نام‌هایی مثل shell.php.jpg هم رد می‌شوند
        $nameParts = array_map('strtolower', explode('.', (string) $file->getClientOriginalName()));
        array_shift($nameParts);
        $nameParts = array_filter($nameParts, fn ($part) => preg_match('/^(php\d*|phtml|pht|phar|htaccess)$/', $part));

        $mime = strtolower((string) $file->getMimeType());
        $badMime = str_contains($mime, 'php') || str_contains($mime, 'x-sh') || str_contains($mime, 'javascript')
            || in_array($mime, ['text/html', 'application/x-httpd-php', 'application/x-msdownload', 'application/x-executable'], true);

        if ($badMime || array_intersect($blocked, array_merge($extensions, $nameParts))) {
            throw ValidationException::withMessages([
                'file' => 'این نوع فایل برای بارگذاری مجاز نیست.',
            ]);
        }
    }

    public function attachExternal(
        string $url,
        Model $model,
        string $collection,
        string $purpose = 'main',
        int $sort = 1,
        ?string $name = null,
        array $meta = []
    ): ?Media
    {
        Validator::make([
            'url' => $url,
        ], [
            'url' => 'required|url|max:2000',
        ])->validate();

        $parsedUrl = parse_url($url);

        $path = $parsedUrl['path'] ?? '';

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        $type = match ($extension) {

            // Images
            'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg' => 'image',

            // Videos
            'mp4', 'mov', 'avi', 'mkv', 'webm' => 'video',

            // Audio
            'mp3', 'wav', 'ogg', 'aac', 'm4a' => 'audio',

            // Documents
            'pdf', 'doc', 'docx' => 'document',

            // Spreadsheet
            'xls', 'xlsx', 'csv' => 'spreadsheet',

            // Presentation
            'ppt', 'pptx' => 'presentation',

            // Archive
            'zip', 'rar', '7z' => 'archive',

            // Text
            'txt', 'md' => 'text',

            default => 'link',
        };

        return Media::create([

            'uuid' => Str::uuid(),

            'name' => $name ?: basename($path) ?: $url,

            'mediable_id' => $model->id,

            'mediable_type' => get_class($model),

            'external_url' => $url,

            'collection' => $collection,

            'extension' => $extension,

            'type' => $type,

            'purpose' => $purpose,

            'sort' => $sort,

            'is_private' => false,

            'meta' => $meta,
        ]);
    }
    public function deleteMedia(\App\Models\Media $media): bool
    {
        try {
            if (!$media->external_url){
                if (Storage::disk($media->disk)->exists($media->file_path)) {
                    Storage::disk($media->disk)->delete($media->file_path);
                }

            }
                return $media->delete();


        } catch (\Exception $e) {
            return false;
        }
    }

}
