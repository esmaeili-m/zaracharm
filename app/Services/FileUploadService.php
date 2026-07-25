<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class FileUploadService
{
    public function upload(UploadedFile $file, string $destinationPath, ?string $oldFileName = null, string $disk = 'public'): ?string
    {
        $validation = Validator::make(
            ['file' => $file],
            ['file' => 'required|image|mimes:jpeg,png,jpg,gif,svg,webp|max:20480']
        );

        if ($validation->fails()) {

            return null;
        }

        $destinationPath = trim($destinationPath, '/');

        if ($oldFileName) {
            $this->delete($oldFileName, '', $disk);
        }

        try {
            $filePath = Storage::disk($disk)->putFile($destinationPath, $file);

            if (!$filePath) {
                return null;
            }

            return $filePath;

        } catch (\Exception $e) {

            return null;
        }
    }

    public function delete(string $fileName, string $destinationPath = '', string $disk = 'public'): bool
    {

        $fullPath = $fileName;

        try {
            if (Storage::disk($disk)->exists($fullPath)) {
                return Storage::disk($disk)->delete($fullPath);
            }
            return false;
        } catch (\Exception $e) {
            return false;
        }
    }
}
