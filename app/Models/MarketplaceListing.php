<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * اتصال یک واریانت فروشگاه به محصول/آگهی مارکت‌پلیس
 */
class MarketplaceListing extends Model
{
    protected $fillable = [
        'marketplace_id', 'product_id', 'product_variant_id',
        'external_id', 'external_variant_id', 'external_sku', 'external_url',
        'is_active', 'sync_content', 'sync_price', 'sync_stock',
        'sync_status', 'synced_price', 'synced_stock', 'synced_active', 'content_hash',
        'failed_attempts', 'last_error', 'last_synced_at', 'meta',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sync_content' => 'boolean',
        'sync_price' => 'boolean',
        'sync_stock' => 'boolean',
        'synced_active' => 'boolean',
        'synced_price' => 'integer',
        'synced_stock' => 'integer',
        'last_synced_at' => 'datetime',
        'meta' => 'array',
    ];

    public const STATUSES = [
        'pending' => 'در انتظار همگام‌سازی',
        'synced' => 'همگام',
        'failed' => 'خطا',
    ];

    public function marketplace(): BelongsTo
    {
        return $this->belongsTo(Marketplace::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id')->withTrashed();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** به محصول/آگهی مارکت‌پلیس وصل شده است؟ */
    public function isLinked(): bool
    {
        return filled($this->external_id);
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->sync_status] ?? (string) $this->sync_status;
    }
}
