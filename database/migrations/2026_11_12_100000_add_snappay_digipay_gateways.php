<?php

use App\Models\PaymentGateway;
use Illuminate\Database\Migrations\Migration;

/**
 * درگاه‌های اسنپ‌پی و دیجی‌پی به‌صورت غیرفعال و بدون اطلاعات اتصال
 * (آدرس API مقدار نمونه است)؛ پس از ورود اطلاعات واقعی از پنل > تنظیمات پرداخت فعال می‌شوند.
 */
return new class extends Migration
{
    protected array $gateways = [
        'snappay' => ['title' => 'اسنپ‌پی (خرید اقساطی)', 'sort' => 10],
        'digipay' => ['title' => 'دیجی‌پی', 'sort' => 11],
    ];

    public function up(): void
    {
        foreach ($this->gateways as $provider => $row) {
            if (PaymentGateway::withTrashed()->where('provider', $provider)->exists()) {
                continue;
            }

            $class = config("payments.providers.{$provider}");
            $settings = $class
                ? collect(app($class)->settingFields())->map(fn ($field) => $field['default'] ?? null)->all()
                : [];

            PaymentGateway::create([
                'provider' => $provider,
                'title' => $row['title'],
                'credentials' => null,
                'settings' => $settings,
                'is_active' => false,
                'is_default' => false,
                'sort' => $row['sort'],
            ]);
        }
    }

    public function down(): void
    {
        PaymentGateway::withTrashed()
            ->whereIn('provider', array_keys($this->gateways))
            ->whereDoesntHave('payments')
            ->forceDelete();
    }
};
