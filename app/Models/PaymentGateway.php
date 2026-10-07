<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * یک درگاه بانکی تعریف‌شده در پنل (Provider + اطلاعات اتصال)
 * credentials با APP_KEY رمزنگاری می‌شود و در خروجی آرایه/JSON نمایش داده نمی‌شود.
 */
class PaymentGateway extends Model
{
    use SoftDeletes;

    protected $fillable = ['provider', 'title', 'credentials', 'settings', 'is_active', 'is_default', 'sort'];

    protected $casts = [
        'credentials' => 'encrypted:array',
        'settings' => 'array',
        'is_active' => 'boolean',
        'is_default' => 'boolean',
    ];

    protected $hidden = ['credentials'];

    public function payments()
    {
        return $this->hasMany(Payment::class, 'gateway_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function credential(string $key, $default = null)
    {
        return ($this->credentials ?? [])[$key] ?? $default;
    }

    public function setting(string $key, $default = null)
    {
        return ($this->settings ?? [])[$key] ?? $default;
    }
}
