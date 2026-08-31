<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Category extends Model
{
    use HasFactory,SoftDeletes;
    protected $guarded=[];


    public function generateSlug($title)
    {
        $slug = preg_replace('/[^a-zA-Z0-9\-_\p{Arabic}]/u', '-', $title);
        $slug = preg_replace('/\s+/u', '-', $slug);
        $slug = preg_replace('/-+/', '-', $slug);
        $slug = trim($slug, '-');
        return $slug;
    }

    public function children(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Category::class,'parent_id');
    }
    public function getAllDescendantIds()
    {
        $ids = collect();

        foreach ($this->children as $child) {
            $ids->push($child->id);
            $ids = $ids->merge($child->getAllDescendantIds());
        }

        return $ids;
    }
    public function discountTargets()
    {
        return $this->morphMany(
            DiscountTarget::class,
            'target'
        );
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

    public function media(): MorphMany
    {
        return $this->morphMany(Media::class, 'mediable');
    }

    public function tags(): MorphToMany
    {
        return $this->morphToMany(Tag::class, 'taggable');
    }
    public function products()
    {
        return $this->belongsToMany(Product::class);

    }
        public function scopeActive($query)
    {
        return $query->where('status', true);
    }
    public function views(): MorphMany
    {
        return $this->morphMany(View::class, 'viewable');
    }
}
