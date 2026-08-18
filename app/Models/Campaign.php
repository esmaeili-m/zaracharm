<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Morilog\Jalali\Jalalian;
use App\Enums\CampaignTargetType;
use App\Models\Product;

class Campaign extends Model
{
    protected $fillable = [
        'title',
        'slug',
        'description',
        'type',
        'status',
        'priority',
        'banner_media_id',
        'start_at',
        'end_at',
        'settings',
    ];

    protected $casts = [
        'settings' => 'array',
        'start_at' => 'datetime',
        'end_at'   => 'datetime',
    ];
    protected function startAt(): Attribute
    {
        return Attribute::make(

            get: function ($value) {

                if (!$value) return null;

                return Jalalian::fromCarbon(
                    Carbon::parse($value)
                )->format('Y/m/d');
            },


            set: function ($value) {
                if (blank($value)) {
                    return null;
                }

                if ($value instanceof Carbon) {
                    return $value->toDateTimeString();
                }

                return Jalalian::fromFormat(
                    'Y/m/d',
                    trim($value)
                )->toCarbon()->toDateTimeString();
            },
        );
    }
    public function media(): MorphMany
    {
        return $this->morphMany(Media::class, 'mediable');
    }
    protected function endAt(): Attribute
    {
    return Attribute::make(

        get: function ($value) {

            if (!$value) return null;

            return Jalalian::fromCarbon(
                Carbon::parse($value)
            )->format('Y/m/d');
        },


        set: function ($value) {
            if (blank($value)) {
                return null;
            }

            if ($value instanceof Carbon) {
                return $value->toDateTimeString();
            }

            return Jalalian::fromFormat(
                'Y/m/d',
                trim($value)
            )->toCarbon()->toDateTimeString();
        },
    );
}
    protected function endAtTimestamp(): Attribute
    {
        return Attribute::make(
            get: function () {

                $value = $this->getRawOriginal('end_at');

                if (!$value) {
                    return null;
                }

                return Carbon::parse($value)->timestamp;
            }
        );
    }
    public function featuredImage()
    {
        return $this->morphOne(Media::class, 'mediable')
            ->where('collection', 'featured_image');
    }
    public function conditions(): HasMany
    {
        return $this->hasMany(CampaignCondition::class);
    }

    public function rewards(): HasMany
    {
        return $this->hasMany(CampaignReward::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', true);
    }
    public function targets(): HasMany
    {
        return $this->hasMany(CampaignTarget::class);
    }

    public function targetProducts()
    {
        $targets = $this->targets;

        // کمپین روی کل فروشگاه
        if ($targets->contains(function ($target) {
            return $target->target_type === CampaignTargetType::ALL;
        })) {
            return Product::query()
                ->where('status', 1);
        }

        $productIds = $targets
            ->where('target_type', CampaignTargetType::PRODUCT)
            ->pluck('target_id')
            ->filter()
            ->values();

        $categoryIds = $targets
            ->where('target_type', CampaignTargetType::CATEGORY)
            ->pluck('target_id')
            ->filter()
            ->values();

        $brandIds = $targets
            ->where('target_type', CampaignTargetType::BRAND)
            ->pluck('target_id')
            ->filter()
            ->values();

        return Product::query()
            ->where('status', 1)
            ->where(function ($query) use (
                $productIds,
                $categoryIds,
                $brandIds
            ) {

                // محصولات مستقیم
                if ($productIds->isNotEmpty()) {

                    $query->whereIn('id', $productIds);
                }

                // محصولات دسته‌بندی
                if ($categoryIds->isNotEmpty()) {

                    $query->orWhereHas('categories', function ($q) use ($categoryIds) {

                        $q->whereIn('categories.id', $categoryIds);

                    });
                }

                // محصولات برند
                if ($brandIds->isNotEmpty()) {

                    $query->orWhereIn('brand_id', $brandIds);
                }
            });
    }
}
