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
    // وضعیت ذخیره‌شده (status). «زمان‌بندی‌شده» و «پایان‌یافته» از تاریخ‌ها محاسبه می‌شوند.
    public const STATUS_DRAFT = 0;    // پیش‌نویس (اعمال نمی‌شود)
    public const STATUS_ACTIVE = 1;   // فعال (در بازه زمانی اعمال می‌شود)
    public const STATUS_PAUSED = 2;   // غیرفعال‌شده توسط مدیر

    // انواعی که موتور قیمت واقعاً اعمال می‌کند (ProductPriceService)
    public const PRICED_TYPES = [0, 1]; // تخفیف ، فروش ویژه (شگفت‌انگیز)

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
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function usages(): HasMany
    {
        return $this->hasMany(CampaignUsage::class);
    }

    /**
     * وضعیت نمایشی: draft | paused | scheduled | active | ended
     */
    public function getStateAttribute(): string
    {
        $status = (int) $this->getRawOriginal('status');
        $start = $this->getRawOriginal('start_at') ? Carbon::parse($this->getRawOriginal('start_at')) : null;
        $end = $this->getRawOriginal('end_at') ? Carbon::parse($this->getRawOriginal('end_at')) : null;

        return match (true) {
            $status === self::STATUS_DRAFT => 'draft',
            $status === self::STATUS_PAUSED => 'paused',
            $end && $end->isPast() => 'ended',
            $start && $start->isFuture() => 'scheduled',
            default => 'active',
        };
    }

    public const STATES = [
        'draft' => ['label' => 'پیش‌نویس', 'class' => 'bg-secondary-transparent'],
        'paused' => ['label' => 'غیرفعال', 'class' => 'bg-warning-transparent'],
        'scheduled' => ['label' => 'زمان‌بندی‌شده', 'class' => 'bg-info-transparent'],
        'active' => ['label' => 'فعال', 'class' => 'bg-success-transparent'],
        'ended' => ['label' => 'پایان‌یافته', 'class' => 'bg-light text-muted'],
    ];

    public function setting(string $key, $default = null)
    {
        return ($this->settings ?? [])[$key] ?? $default;
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
            return (int) $target->target_type === CampaignTargetType::ALL->value;
        })) {
            return Product::query()
                ->where('status', 1);
        }

        $productIds = $targets
            ->where('target_type', CampaignTargetType::PRODUCT->value)
            ->pluck('target_id')
            ->filter()
            ->values();

        $categoryIds = $targets
            ->where('target_type', CampaignTargetType::CATEGORY->value)
            ->pluck('target_id')
            ->filter()
            ->values();

        $brandIds = $targets
            ->where('target_type', CampaignTargetType::BRAND->value)
            ->pluck('target_id')
            ->filter()
            ->values();

        // کمپین بدون هدف => هیچ محصولی (نه کل فروشگاه)
        if ($productIds->isEmpty() && $categoryIds->isEmpty() && $brandIds->isEmpty()) {
            return Product::query()->whereRaw('1 = 0');
        }

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
