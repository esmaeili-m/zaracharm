<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ReturnRequest extends Model
{
    use SoftDeletes;

    // وضعیت‌ها (مثل بقیه پروژه رشته‌ای هستند)
    public const STATUSES = [
        'pending' => 'در انتظار بررسی',
        'approved' => 'تأیید شده - ارسال کالا',
        'received' => 'کالا دریافت شد',
        'refunded' => 'وجه بازگردانده شد',
        'rejected' => 'رد شده',
        'cancelled' => 'لغو شده',
    ];

    // وضعیت‌هایی که اقلامشان هنوز «در جریان مرجوعی» حساب می‌شود
    public const OPEN_STATUSES = ['pending', 'approved', 'received', 'refunded'];

    public const REASONS = [
        'damaged' => 'کالا آسیب‌دیده یا معیوب است',
        'wrong_item' => 'کالای اشتباه ارسال شده',
        'not_as_described' => 'کالا با توضیحات سایت مطابقت ندارد',
        'incomplete' => 'کالا ناقص است یا متعلقات ندارد',
        'changed_mind' => 'از خرید منصرف شده‌ام',
        'other' => 'سایر موارد',
    ];

    protected $fillable = [
        'return_number',
        'order_id',
        'user_id',
        'status',
        'reason',
        'description',
        'admin_note',
        'refund_amount',
        'refunded_at',
    ];

    protected function casts(): array
    {
        return [
            'refund_amount' => 'integer',
            'refunded_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ReturnRequestItem::class);
    }

    public function transactions(): MorphMany
    {
        return $this->morphMany(Transaction::class, 'reference');
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', self::OPEN_STATUSES);
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? 'نامشخص';
    }

    public function getReasonLabelAttribute(): string
    {
        return self::REASONS[$this->reason] ?? $this->reason;
    }

    // کلاس رنگ وضعیت برای Frontend (Tailwind)
    public function getStatusClassAttribute(): string
    {
        return match ($this->status) {
            'pending' => 'bg-amber-500/10 border-amber-500/20 text-amber-600 dark:text-amber-400',
            'approved' => 'bg-blue-500/10 border-blue-500/20 text-blue-600 dark:text-blue-400',
            'received' => 'bg-purple-500/10 border-purple-500/20 text-purple-600 dark:text-purple-400',
            'refunded' => 'bg-emerald-500/10 border-emerald-500/20 text-emerald-600 dark:text-emerald-400',
            'rejected' => 'bg-red-500/10 border-red-500/20 text-red-600 dark:text-red-400',
            default => 'bg-gray-500/10 border-gray-500/20 text-gray-500',
        };
    }

    // کلاس badge وضعیت برای داشبورد (Bootstrap)
    public function getStatusBadgeAttribute(): string
    {
        return match ($this->status) {
            'pending' => 'warning',
            'approved' => 'info',
            'received' => 'primary',
            'refunded' => 'success',
            'rejected' => 'danger',
            default => 'secondary',
        };
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }
}
