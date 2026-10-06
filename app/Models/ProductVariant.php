<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use App\Services\Pricing\ProductPriceService;
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


// موجودی قابل فروش = quantity - reserved (فقط ردیف‌ها و انبارهای فعال)
    public function availableStock(): int
    {
        $items = $this->relationLoaded('inventoryItems')
            ? $this->inventoryItems
            : $this->inventoryItems()->with('inventory')->get();

        return (int) $items
            ->filter(fn ($item) => $item->status && $item->inventory?->status)
            ->sum(fn ($item) => max($item->quantity - $item->reserved_quantity, 0));
    }

    public function isInStock(): bool
    {
        return $this->availableStock() > 0;
    }
    public function priceData(): array
    {
        return app(ProductPriceService::class)
            ->calculate($this);
    }
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
    public function orderItems()
    {
        return $this->hasMany(OrderItem::class, 'variant_id');
    }
    public function inventoryItems()
    {
        return $this->hasMany(InventoryItem::class, 'product_variant_id');
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

    // عنوان تنوع: مقادیر ویژگی‌ها (مثلاً «قرمز / XL») یا SKU
    public function getLabelAttribute(): string
    {
        $values = $this->relationLoaded('values') ? $this->values : $this->values()->get();

        return $values->pluck('title')->filter()->implode(' / ') ?: ($this->sku ?: 'پیش‌فرض');
    }
}
