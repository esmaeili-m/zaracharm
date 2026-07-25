<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Morilog\Jalali\Jalalian;
class Article extends Model
{
    use SoftDeletes,HasFactory;
    protected $guarded= [];
    protected $casts = [
        'published_at' => 'datetime',
    ];
    protected function publishedAt(): Attribute
    {
        return Attribute::make(

            get: function ($value) {

                if (!$value) return null;

                return Jalalian::fromCarbon(
                    Carbon::parse($value)
                )->format('Y/m/d');
            },


            set: fn ($value) => $value
                ? Jalalian::fromFormat('Y/m/d', $value)->toCarbon()
                : null,
        );
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
                ? url('/media/' . $this->featuredImage->file_path)
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
                ? url('/media/' . $this->bannerImage->file_path)
                : null
        );
    }

    public function media(): MorphMany
    {
        return $this->morphMany(Media::class, 'mediable');
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }
    public function tags(): MorphToMany
    {
        return $this->morphToMany(Tag::class, 'taggable');
    }

    public function comments(): MorphMany
    {
        return $this->morphMany(Comment::class, 'commentable')->where('is_approved', 1);;
    }
    public function scopeActive($query)
    {
        return $query->where('status', true);
    }

    public function author()
    {
        return $this->belongsTo(User::class,'author_id');
    }

}
