<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * دسته‌بندی هزینه / درآمد
 * affects_profit = false برای مواردی مثل آورده مالک، برداشت مالک و تسویه مارکت‌پلیس
 * (جابه‌جایی پول هستند، نه سود یا هزینه)
 */
class AccountingCategory extends Model
{
    use SoftDeletes;

    public const TYPES = [
        'expense' => 'هزینه',
        'income' => 'درآمد',
    ];

    protected $fillable = ['type', 'title', 'affects_profit', 'status', 'sort'];

    protected $casts = [
        'affects_profit' => 'boolean',
        'status' => 'boolean',
        'sort' => 'integer',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', true);
    }

    public function entries(): HasMany
    {
        return $this->hasMany(AccountingEntry::class, 'category_id');
    }

    public function getTypeLabelAttribute(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }
}
