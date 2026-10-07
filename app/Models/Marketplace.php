<?php

namespace App\Models;

use App\Marketplaces\Contracts\MarketplaceProvider;
use App\Marketplaces\MarketplaceManager;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * یک مارکت‌پلیس متصل (Provider + اطلاعات اتصال + تنظیمات + وضعیت)
 * credentials با APP_KEY رمزنگاری می‌شود و در خروجی آرایه/JSON نمایش داده نمی‌شود.
 */
class Marketplace extends Model
{
    protected $fillable = [
        'provider', 'title', 'credentials', 'settings', 'is_active', 'auto_sync', 'webhook_secret',
        'connection_status', 'connection_message', 'connection_checked_at',
        'last_product_sync_at', 'last_stock_sync_at', 'last_order_sync_at', 'order_cursor',
        'last_error_at', 'last_error',
    ];

    protected $casts = [
        'credentials' => 'encrypted:array',
        'settings' => 'array',
        'is_active' => 'boolean',
        'auto_sync' => 'boolean',
        'connection_checked_at' => 'datetime',
        'last_product_sync_at' => 'datetime',
        'last_stock_sync_at' => 'datetime',
        'last_order_sync_at' => 'datetime',
        'last_error_at' => 'datetime',
    ];

    protected $hidden = ['credentials', 'webhook_secret'];

    public function listings(): HasMany
    {
        return $this->hasMany(MarketplaceListing::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(MarketplaceOrder::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(MarketplaceSyncLog::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function credential(string $key, $default = null)
    {
        $value = ($this->credentials ?? [])[$key] ?? null;

        return filled($value) ? $value : $default;
    }

    /** تنظیم ذخیره‌شده یا مقدار پیش‌فرض تعریف‌شده در Provider */
    public function setting(string $key, $default = null)
    {
        $settings = $this->settings ?? [];

        if (array_key_exists($key, $settings) && $settings[$key] !== null && $settings[$key] !== '') {
            return $settings[$key];
        }

        if ($default !== null) {
            return $default;
        }

        return $this->hasDriver() ? ($this->driver()->settingFields()[$key]['default'] ?? null) : null;
    }

    public function hasDriver(): bool
    {
        return app(MarketplaceManager::class)->has($this->provider);
    }

    public function driver(): MarketplaceProvider
    {
        return app(MarketplaceManager::class)->provider($this->provider);
    }

    public function supports(string $capability): bool
    {
        return $this->hasDriver() && in_array($capability, $this->driver()->capabilities(), true);
    }

    public function isConfigured(): bool
    {
        return $this->hasDriver() && $this->driver()->isConfigured($this);
    }

    /** فعال + تنظیم‌شده => قابل استفاده برای همگام‌سازی */
    public function isUsable(): bool
    {
        return $this->is_active && $this->isConfigured();
    }

    public function mergeSettings(array $values): void
    {
        $this->forceFill(['settings' => array_merge((array) $this->settings, $values)])->save();
    }
}
