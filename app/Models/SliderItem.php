<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class SliderItem extends Model
{
    protected $guarded=[];

    public function media(): MorphMany
    {
        return $this->morphMany(Media::class, 'mediable')
            ->orderBy('sort');
    }
    public function featuredImage()
    {
        return $this->morphOne(Media::class, 'mediable')
            ->where('collection', 'featured_image');
    }
    public function getFeaturedImageUrlAttribute()
    {
        return $this->featuredImage
            ? url('/storage/' . $this->featuredImage->file_path)
            : null;
    }
}
