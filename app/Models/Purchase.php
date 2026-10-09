<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Morilog\Jalali\Jalalian;

/**
 * فاکتور خرید از تأمین‌کننده
 * draft = پیش‌نویس (بدون اثر روی انبار) | received = دریافت‌شده (کالا به انبار اضافه شده) | cancelled = لغو
 */
class Purchase extends Model
{
    use SoftDeletes;

    public const STATUSES = [
        'draft' => 'پیش‌نویس',
        'received' => 'دریافت‌شده',
        'cancelled' => 'لغو شده',
    ];

    public const STATUS_COLORS = [
        'draft' => 'secondary',
        'received' => 'success',
        'cancelled' => 'danger',
    ];

    protected $fillable = [
        'purchase_number', 'supplier_id', 'inventory_id', 'status', 'purchase_date', 'supplier_invoice_number',
        'subtotal', 'discount_amount', 'total_amount', 'update_cost_price', 'note', 'received_at', 'created_by',
    ];

    protected $casts = [
        'purchase_date' => 'date',
        'received_at' => 'datetime',
        'subtotal' => 'integer',
        'discount_amount' => 'integer',
        'total_amount' => 'integer',
        'update_cost_price' => 'boolean',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class)->withTrashed();
    }

    public function inventory(): BelongsTo
    {
        return $this->belongsTo(Inventory::class)->withTrashed();
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(AccountingEntry::class)->where('source', 'supplier_payment');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function getStatusColorAttribute(): string
    {
        return self::STATUS_COLORS[$this->status] ?? 'secondary';
    }

    public function getJalaliDateAttribute(): string
    {
        return $this->purchase_date ? Jalalian::fromCarbon($this->purchase_date)->format('Y/m/d') : '';
    }
}
