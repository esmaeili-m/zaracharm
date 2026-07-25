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
        $meta=[]
    ): ?Media
    {
        Validator::make(
            ['file' => $file],
            ['file' => 'required|file|max:512000']
        )->validate();

        $path = $file->store($collection, $disk);

        if (!$path) return null;

        $mime = $file->getMimeType();
        $extension = strtolower($file->getClientOriginalExtension());

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

        'extension' => $file->getClientOriginalExtension(),

        'size' => $file->getSize(),

        'type' => $type,

        'is_private' => false,

        'purpose' => $purpose,

        'sort' => $sort,

        'meta' => $meta
    ]);
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
