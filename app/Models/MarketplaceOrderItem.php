<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketplaceOrderItem extends Model
{
    protected $fillable = [
        'marketplace_order_id', 'marketplace_listing_id', 'product_variant_id',
        'external_item_id', 'external_product_id', 'external_variant_id',
        'title', 'quantity', 'price', 'total', 'stock_deducted', 'allocations',
    ];

    protected $casts = [
        'allocations' => 'array',
        'quantity' => 'integer',
        'price' => 'integer',
        'total' => 'integer',
        'stock_deducted' => 'integer',
    ];

    // قیمت خرید برای گزارش سود؛ واریانت ممکن است بعداً (هنگام اتصال لیستینگ) مشخص شود => saving
    protected static function booted(): void
    {
        static::saving(fn ($item) => \App\Services\Accounting\CostSnapshot::fill($item, 'product_variant_id'));
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(MarketplaceOrder::class, 'marketplace_order_id');
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(MarketplaceListing::class, 'marketplace_listing_id');
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id')->withTrashed();
    }
}
