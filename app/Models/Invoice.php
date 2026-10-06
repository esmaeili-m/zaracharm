<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Invoice extends Model
{
    // جدول invoices از ابتدا ستون deleted_at داشته است
    use SoftDeletes;

    protected $guarded=[];

    protected $casts = [
        'paid_at' => 'datetime',
    ];

    public const STATUSES = [
        'draft'     => 'پیش‌نویس',
        'unpaid'    => 'پرداخت‌نشده',
        'paid'      => 'پرداخت‌شده',
        'cancelled' => 'لغو شده',
        'refunded'  => 'بازگشت وجه',
    ];

    public const SOURCES = [
        'online' => 'سفارش سایت',
        'manual' => 'صادرشده توسط مدیر',
        'pos'    => 'فروش حضوری',
    ];

    public function isOnline(): bool
    {
        return $this->order_id !== null;
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function stockMovements()
    {
        return $this->morphMany(StockMovement::class, 'reference')->latest('id');
    }

    public function getCustomerLabelAttribute(): string
    {
        if ($this->user) {
            return trim($this->user->full_name) ?: (string) $this->user->mobile;
        }

        return $this->customer_name ?: ($this->customer_mobile ?: 'مشتری حضوری');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // ======================
    // آیتم‌های فاکتور
    // ======================
    public function items()
    {
        return $this->hasMany(InvoiceItem::class);
    }
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    // ======================
    // پرداخت‌ها
    // (درگاه / کیف پول / retry)
    // ======================
    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    // ======================
    // آخرین پرداخت موفق
    // ======================
    public function successfulPayment()
    {
        return $this->hasOne(Payment::class)
            ->where('status', 'success')
            ->latestOfMany();
    }

    // ======================
    // جمع قیمت آیتم‌ها (computed)
    // ======================
    public function getItemsTotalAttribute()
    {
        return $this->items->sum('total');
    }

    // ======================
    // آیا پرداخت شده؟
    // ======================
    public function getIsPaidAttribute()
    {
        return $this->status === 'paid';
    }
}
