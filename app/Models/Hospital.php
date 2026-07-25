<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Hospital extends Model
{
    protected $guarded=[];
    public function scopeActive($query)
    {
        return $query->where('status', true);
    }

    public function featuredImage()
    {
        return $this->morphOne(Media::class, 'mediable')
            ->where('collection', 'featured_image');
    }

    protected function featuredImageUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->featuredImage
                ? url('/storage/' . $this->featuredImage->file_path)
                : null
        );
    }

    public function bannerImage()
    {
        return $this->morphOne(Media::class, 'mediable')
            ->where('collection', 'banner_image');
    }

    protected function bannerImageUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->bannerImage
                ? url('/storage/' . $this->bannerImage->file_path)
                : null
        );
    }

    public function media(): MorphMany
    {
        return $this->morphMany(Media::class, 'mediable');
    }
}
