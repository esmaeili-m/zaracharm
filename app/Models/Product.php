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
use App\Models\DiscountTarget;

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
    public function views(): MorphMany
    {
        return $this->morphMany(View::class, 'viewable');
    }
    public function ratings()
    {
        return $this->morphMany(Rating::class, 'rateable');
    }

    public function productSpecifications()
    {
        return $this->hasMany(ProductSpecification::class);
    }
    public function specifications()
    {
        return $this->belongsToMany(
            Specification::class,
            'product_specifications'
        )->withPivot([
            'text_value',
            'number_value',
            'decimal_value',
            'boolean_value',
            'date_value',
            'status',
        ]);
    }

    public function options()
    {
        return $this->belongsToMany(
            Option::class,
            'product_options',
            'product_id',
            'option_id'
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
    public function primaryCategory()
    {
        return $this->belongsTo(Category::class, 'primary_category_id');
    }

    public function comments(): MorphMany
    {
        return $this->morphMany(Comment::class, 'commentable')->where('is_approved', 1);;
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

    public function mainProduct()
    {
        return $this->hasOne(ProductVariant::class)
            ->where('status', 1)->where('is_default',1);
    }
    public function discountTargets()
    {
        return $this->morphMany(
            DiscountTarget::class,
            'target'
        );
    }
    public function displayVariant()
    {
        return $this->hasOne(ProductVariant::class)
            ->where('status', 1)
            ->whereHas('inventoryItems', function ($q) {
                $q->where('status', 1)
                    ->whereRaw('quantity > reserved_quantity')
                    ->whereHas('inventory', function ($q) {
                        $q->where('status', 1);
                    });
            })
            ->orderByRaw("
            CASE
                WHEN compare_price IS NOT NULL
                     AND compare_price > price THEN 0
                ELSE 1
            END
        ")
            ->orderBy('price');
    }
    public function tags(): MorphToMany
    {
        return $this->morphToMany(Tag::class, 'taggable');
    }

    public function cheapestVariant()
    {
        return $this->hasOne(ProductVariant::class)
            ->orderBy('price', 'asc');
    }
}
