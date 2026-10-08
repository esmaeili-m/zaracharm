<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Story extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'type',
        'user',
        'avatar',
        'url',
        'duration',
        'link',
        'status',
        'sort',
    ];

    protected $casts = [
        'duration' => 'integer',
        'status' => 'boolean',
        'sort' => 'integer',
    ];

    public function media(): MorphMany
    {
        return $this->morphMany(Media::class, 'mediable');
    }

    /** اسلایدهای استوری (تصویر/ویدیو) به ترتیب نمایش */
    public function items(): HasMany
    {
        return $this->hasMany(StoryItem::class)->orderBy('sort')->orderBy('id');
    }

    public function getAvatarUrlAttribute(): ?string
    {
        $avatar = $this->relationLoaded('media')
            ? $this->media->firstWhere('collection', 'avatar')
            : $this->media()->where('collection', 'avatar')->latest('id')->first();

        return $avatar ? asset('storage/' . $avatar->file_path) : null;
    }
    public function scopeActive($query)
    {
        return $query->where('status', true);
    }
}
