<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Morilog\Jalali\Jalalian;

/**
 * یک ردیف دفتر دریافت و پرداخت
 * type: income (ورود پول به حساب) | expense (خروج پول از حساب) | transfer (از account به to_account)
 */
class AccountingEntry extends Model
{
    use SoftDeletes;

    public const TYPES = [
        'income' => 'دریافت / درآمد',
        'expense' => 'پرداخت / هزینه',
        'transfer' => 'انتقال بین حساب‌ها',
    ];

    public const SOURCES = [
        'manual' => 'ثبت دستی',
        'payment' => 'پرداخت سفارش سایت',
        'payment_refund' => 'بازگشت وجه سفارش',
        'invoice' => 'فروش حضوری / فاکتور',
        'invoice_refund' => 'بازگشت وجه فاکتور',
        'supplier_payment' => 'پرداخت به تأمین‌کننده',
    ];

    // ردیف‌های خودکار فقط حذف می‌شوند (ویرایش مبلغ آن‌ها باعث ناهمخوانی با پرداخت/فاکتور می‌شود)
    public const AUTO_SOURCES = ['payment', 'payment_refund', 'invoice', 'invoice_refund'];

    protected $fillable = [
        'type', 'source', 'account_id', 'to_account_id', 'category_id', 'supplier_id', 'purchase_id',
        'amount', 'entry_date', 'title', 'description', 'reference_type', 'reference_id', 'created_by',
    ];

    protected $casts = [
        'amount' => 'integer',
        'entry_date' => 'date',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(AccountingAccount::class, 'account_id')->withTrashed();
    }

    public function toAccount(): BelongsTo
    {
        return $this->belongsTo(AccountingAccount::class, 'to_account_id')->withTrashed();
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(AccountingCategory::class, 'category_id')->withTrashed();
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class)->withTrashed();
    }

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class)->withTrashed();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    public function media(): MorphMany
    {
        return $this->morphMany(Media::class, 'mediable');
    }

    public function receipt(): MorphOne
    {
        return $this->morphOne(Media::class, 'mediable')->where('collection', 'receipt')->latestOfMany();
    }

    public function getReceiptUrlAttribute(): ?string
    {
        $file = $this->relationLoaded('receipt') ? $this->receipt : $this->receipt()->first();

        return $file ? url('/storage/' . $file->file_path) : null;
    }

    public function getTypeLabelAttribute(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    public function getSourceLabelAttribute(): string
    {
        return self::SOURCES[$this->source] ?? $this->source;
    }

    public function getJalaliDateAttribute(): string
    {
        return $this->entry_date ? Jalalian::fromCarbon($this->entry_date)->format('Y/m/d') : '';
    }

    public function isAuto(): bool
    {
        return in_array($this->source, self::AUTO_SOURCES, true);
    }
}
