<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * حساب مالی (صندوق / بانک)
 * مانده = مانده اول دوره + دریافت‌ها − پرداخت‌ها − انتقال‌های خروجی + انتقال‌های ورودی
 */
class AccountingAccount extends Model
{
    use SoftDeletes;

    public const TYPES = [
        'cash' => 'صندوق (نقدی)',
        'bank' => 'حساب بانکی',
        'other' => 'سایر',
    ];

    protected $fillable = [
        'title', 'type', 'account_number', 'bank_card_id', 'payment_gateway_id',
        'opening_balance', 'is_default', 'status', 'sort', 'description',
    ];

    protected $casts = [
        'opening_balance' => 'integer',
        'is_default' => 'boolean',
        'status' => 'boolean',
        'sort' => 'integer',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', true);
    }

    public function bankCard(): BelongsTo
    {
        return $this->belongsTo(BankCard::class)->withTrashed();
    }

    public function gateway(): BelongsTo
    {
        return $this->belongsTo(PaymentGateway::class, 'payment_gateway_id')->withTrashed();
    }

    public function entries(): HasMany
    {
        return $this->hasMany(AccountingEntry::class, 'account_id');
    }

    public function getTypeLabelAttribute(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    public static function default(): ?self
    {
        return static::active()->where('is_default', true)->orderBy('sort')->first()
            ?? static::active()->orderBy('sort')->first();
    }

    /**
     * مانده همه حساب‌ها با یک کوئری: [account_id => balance]
     */
    public static function balances(?string $until = null): array
    {
        $rows = AccountingEntry::query()
            ->when($until, fn ($q) => $q->where('entry_date', '<=', $until))
            ->selectRaw("account_id,
                SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END) as income,
                SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END) as expense,
                SUM(CASE WHEN type = 'transfer' THEN amount ELSE 0 END) as transfer_out")
            ->groupBy('account_id')
            ->get()
            ->keyBy('account_id');

        $transferIn = AccountingEntry::query()
            ->where('type', 'transfer')
            ->when($until, fn ($q) => $q->where('entry_date', '<=', $until))
            ->selectRaw('to_account_id, SUM(amount) as total')
            ->groupBy('to_account_id')
            ->pluck('total', 'to_account_id');

        return static::withTrashed()->get(['id', 'opening_balance'])
            ->mapWithKeys(function ($account) use ($rows, $transferIn) {
                $row = $rows[$account->id] ?? null;

                return [$account->id => (int) $account->opening_balance
                    + (int) ($row->income ?? 0)
                    - (int) ($row->expense ?? 0)
                    - (int) ($row->transfer_out ?? 0)
                    + (int) ($transferIn[$account->id] ?? 0)];
            })
            ->all();
    }
}
