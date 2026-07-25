<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
class ProductVariant extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'product_id',
        'sku',
        'barcode',
        'price',
        'compare_price',
        'cost_price',
        'weight',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'status' => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function optionValues(): HasMany
    {
        return $this->hasMany(ProductVariantOptionValue::class);
    }
    public function values(): BelongsToMany
    {
        return $this->belongsToMany(
            OptionValue::class,
            'product_variant_option_values',
            'product_variant_id',
            'option_value_id'
        );
    }
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', true);
    }
}
