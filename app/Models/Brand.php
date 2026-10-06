<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use App\Models\DiscountTarget;

class Brand extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'title',
        'slug',
        'description',
        'website',
        'country',
        'sort',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'status' => 'boolean',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Relations
    |--------------------------------------------------------------------------
    */

    public function discountTargets()
    {
        return $this->morphMany(
            DiscountTarget::class,
            'target'
        );
    }
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function media(): MorphMany
    {
        return $this->morphMany(Media::class, 'mediable')
            ->orderBy('sort');
    }

    // لوگوی برند (در داشبورد برندها با collection = featured_image آپلود می‌شود)
    public function logo()
    {
        return $this->morphOne(Media::class, 'mediable')
            ->where('collection', 'featured_image');
    }

    public function bannerImage()
    {
        return $this->morphOne(Media::class, 'mediable')
            ->where('collection', 'banner_image');
    }

    public function getLogoUrlAttribute()
    {
        return $this->logo
            ? url('/storage/' . $this->logo->file_path)
            : null;
    }

    public function getBannerImageUrlAttribute()
    {
        return $this->bannerImage
            ? url('/storage/' . $this->bannerImage->file_path)
            : null;
    }

    // محصولات قابل نمایش برند در فروشگاه
    public function activeProducts(): HasMany
    {
        return $this->hasMany(Product::class)
            ->active()
            ->has('variants');
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    public function scopeActive($query)
    {
        return $query->where('status', true);
    }
}
