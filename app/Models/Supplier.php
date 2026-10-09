<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * تأمین‌کننده
 * بدهی ما = مانده اول دوره + جمع خریدهای دریافت‌شده − پرداخت‌ها به تأمین‌کننده
 */
class Supplier extends Model
{
    use SoftDeletes;

    protected $fillable = ['title', 'contact_name', 'mobile', 'phone', 'address', 'opening_balance', 'description', 'status'];

    protected $casts = [
        'opening_balance' => 'integer',
        'status' => 'boolean',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', true);
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(Purchase::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(AccountingEntry::class)->where('source', 'supplier_payment');
    }

    /**
     * بدهی به همه تأمین‌کنندگان: [supplier_id => balance]
     */
    public static function balances(): array
    {
        $purchased = Purchase::where('status', 'received')
            ->selectRaw('supplier_id, SUM(total_amount) as total')
            ->groupBy('supplier_id')
            ->pluck('total', 'supplier_id');

        $paid = AccountingEntry::where('source', 'supplier_payment')
            ->selectRaw('supplier_id, SUM(amount) as total')
            ->groupBy('supplier_id')
            ->pluck('total', 'supplier_id');

        return static::withTrashed()->get(['id', 'opening_balance'])
            ->mapWithKeys(fn ($s) => [$s->id => (int) $s->opening_balance + (int) ($purchased[$s->id] ?? 0) - (int) ($paid[$s->id] ?? 0)])
            ->all();
    }
}
