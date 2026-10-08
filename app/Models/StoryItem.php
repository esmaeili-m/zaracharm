<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

/**
 * یک اسلاید استوری (تصویر یا ویدیو) با عنوان و توضیح اختیاری
 * فایل در media با collection = story_media
 */
class StoryItem extends Model
{
    protected $fillable = [
        'story_id',
        'type',
        'title',
        'description',
        'duration',
        'link',
        'sort',
    ];

    protected $casts = [
        'duration' => 'integer',
        'sort' => 'integer',
    ];

    public function story(): BelongsTo
    {
        return $this->belongsTo(Story::class);
    }

    public function media(): MorphMany
    {
        return $this->morphMany(Media::class, 'mediable');
    }

    public function file(): MorphOne
    {
        return $this->morphOne(Media::class, 'mediable')->where('collection', 'story_media')->latestOfMany();
    }

    public function getFileUrlAttribute(): ?string
    {
        $file = $this->relationLoaded('file') ? $this->file : $this->file()->first();

        if (! $file) {
            return null;
        }

        return $file->external_url ?: asset('storage/' . $file->file_path);
    }
}
