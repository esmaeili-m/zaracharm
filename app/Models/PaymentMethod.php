<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * تنظیمات نمایش/فعال‌سازی یک روش پرداخت؛ منطق در Driver متناظر (config/payments.php)
 */
class PaymentMethod extends Model
{
    protected $fillable = ['key', 'title', 'description', 'is_active', 'sort', 'settings'];

    protected $casts = [
        'is_active' => 'boolean',
        'settings' => 'array',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort')->orderBy('id');
    }
}
