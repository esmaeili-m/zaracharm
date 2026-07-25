<?php

namespace App\Models;

use App\Enums\ProductType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;
use App\Models\ProductSpecification;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Morilog\Jalali\Jalalian;

class Product extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'brand_id',
        'title',
        'slug',
        'short_description',
        'description',
        'type',
        'is_featured',
        'status',
        'sort',
        'view_count',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => ProductType::class,
            'status' => 'boolean',
            'is_featured' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Relations
    |--------------------------------------------------------------------------
    */

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function media(): MorphMany
    {
        return $this->morphMany(Media::class, 'mediable')
            ->orderBy('sort');
    }

    public function mainImage()
    {
        return $this->featuredImage
            ? url('/storage/' . $this->featuredImage->file_path)
            : null;
    }

    public function productSpecifications()
    {
        return $this->hasMany(ProductSpecification::class);
    }

    public function specifications(): BelongsToMany
    {
        return $this->belongsToMany(
            Specification::class,
            'product_specifications',
            'product_id',
            'specification_id'
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
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class);
    }


    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', true);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query
            ->where('status', true)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where('is_featured', true);
    }

    protected function publishedAt(): Attribute
    {

        return Attribute::make(

            get: fn ($value) => $value
                ? Jalalian::fromCarbon(Carbon::parse($value))
                    ->format('Y/m/d')
                : null,
            // هنگام ذخیره در دیتابیس
            set: fn ($value) => blank($value)
                ? null
                : Jalalian::fromFormat('Y/m/d', $value)->toCarbon(),
        );
    }

    public function tags(): MorphToMany
    {
        return $this->morphToMany(Tag::class, 'taggable');
    }

}
