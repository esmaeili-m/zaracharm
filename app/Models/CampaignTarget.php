<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CampaignTarget extends Model
{
    protected $fillable = [
        'campaign_id',
        'target_type',
        'target_id',
    ];

    /*
    |--------------------------------------------------------------------------
    | Target Types
    |--------------------------------------------------------------------------
    */

    public const TYPE_ALL = 0;
    public const TYPE_PRODUCT = 1;
    public const TYPE_CATEGORY = 2;
    public const TYPE_BRAND = 3;
    public const TYPE_COLLECTION = 4;


    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'target_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'target_id');
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class, 'target_id');
    }




    /*
    |--------------------------------------------------------------------------
    | Accessors
    |--------------------------------------------------------------------------
    */

    public function getTargetNameAttribute(): string
    {
        return match ($this->target_type) {

            self::TYPE_ALL =>
            'کل فروشگاه',

            self::TYPE_PRODUCT =>
                $this->product?->title ?? '-',

            self::TYPE_CATEGORY =>
                $this->category?->title ?? '-',

            self::TYPE_BRAND =>
                $this->brand?->title ?? '-',

            self::TYPE_COLLECTION =>
                $this->collection?->title ?? '-',

            default =>
            '-',
        };
    }

    public function getTargetTypeNameAttribute(): string
    {
        return match ($this->target_type) {

            self::TYPE_ALL =>
            'کل فروشگاه',

            self::TYPE_PRODUCT =>
            'محصول',

            self::TYPE_CATEGORY =>
            'دسته‌بندی',

            self::TYPE_BRAND =>
            'برند',

            self::TYPE_COLLECTION =>
            'مجموعه',

            default =>
            '-',
        };
    }
}
