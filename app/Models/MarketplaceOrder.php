<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * سفارش دریافتی از مارکت‌پلیس (پرداخت در خود مارکت‌پلیس انجام شده است)
 */
class MarketplaceOrder extends Model
{
    protected $fillable = [
        'marketplace_id', 'external_id', 'external_order_id', 'external_status', 'status', 'payment_status',
        'customer_name', 'customer_mobile', 'province', 'city', 'address', 'postal_code',
        'items_amount', 'shipping_amount', 'total_amount', 'tracking_code',
        'stock_status', 'admin_note', 'ordered_at', 'paid_at', 'synced_at', 'payload',
    ];

    protected $casts = [
        'payload' => 'array',
        'ordered_at' => 'datetime',
        'paid_at' => 'datetime',
        'synced_at' => 'datetime',
        'items_amount' => 'integer',
        'shipping_amount' => 'integer',
        'total_amount' => 'integer',
    ];

    public const STATUSES = [
        'new' => 'جدید',
        'processing' => 'در حال آماده‌سازی',
        'shipped' => 'ارسال شده',
        'delivered' => 'تحویل شده',
        'problem' => 'دارای مشکل',
        'cancelled' => 'لغو شده',
        'returned' => 'مرجوع شده',
    ];

    public const PAYMENT_STATUSES = [
        'paid' => 'پرداخت‌شده',
        'unpaid' => 'پرداخت‌نشده',
        'refunded' => 'بازگشت وجه',
    ];

    public const STOCK_STATUSES = [
        'pending' => 'کسر نشده',
        'applied' => 'کسر شده',
        'partial' => 'کسر ناقص (کمبود موجودی)',
        'reverted' => 'بازگردانده شده',
        'skipped' => 'بدون کالای متصل',
    ];

    // وضعیت‌هایی که کالا از انبار فروشگاه خارج شده/می‌شود
    public const SOLD_STATUSES = ['new', 'processing', 'shipped', 'delivered', 'problem'];

    // وضعیت‌هایی که موجودی باید به انبار برگردد
    public const RELEASED_STATUSES = ['cancelled', 'returned'];

    public function marketplace(): BelongsTo
    {
        return $this->belongsTo(Marketplace::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(MarketplaceOrderItem::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(MarketplaceSyncLog::class)->latest('id');
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? (string) $this->status;
    }

    public function getStockStatusLabelAttribute(): string
    {
        return self::STOCK_STATUSES[$this->stock_status] ?? (string) $this->stock_status;
    }
}
