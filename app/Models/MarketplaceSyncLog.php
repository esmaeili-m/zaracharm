<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketplaceSyncLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'marketplace_id', 'marketplace_listing_id', 'marketplace_order_id',
        'operation', 'direction', 'status', 'method', 'url', 'http_status',
        'request', 'response', 'message', 'attempt', 'duration_ms', 'retryable', 'retried_at',
    ];

    protected $casts = [
        'request' => 'array',
        'response' => 'array',
        'retryable' => 'boolean',
        'retried_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    public const OPERATIONS = [
        'connection' => 'تست اتصال',
        'auth' => 'دریافت/تمدید توکن',
        'listing.create' => 'ایجاد محصول',
        'listing.update' => 'به‌روزرسانی محصول',
        'listing.upload' => 'بارگذاری تصویر',
        'stock' => 'موجودی/قیمت',
        'remote.listings' => 'دریافت فهرست محصولات',
        'orders.pull' => 'دریافت سفارش‌ها',
        'order.import' => 'ثبت سفارش',
        'order.status' => 'تغییر وضعیت سفارش',
        'webhook' => 'وب‌هوک',
        'feed' => 'خوراک محصولات',
        'sync' => 'همگام‌سازی',
    ];

    public function marketplace(): BelongsTo
    {
        return $this->belongsTo(Marketplace::class);
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(MarketplaceListing::class, 'marketplace_listing_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(MarketplaceOrder::class, 'marketplace_order_id');
    }

    public function getOperationLabelAttribute(): string
    {
        return self::OPERATIONS[$this->operation] ?? $this->operation;
    }
}
