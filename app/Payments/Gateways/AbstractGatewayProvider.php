<?php

namespace App\Payments\Gateways;

use App\Models\Order;
use App\Models\PaymentGateway;
use App\Payments\Contracts\GatewayProvider;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * پیاده‌سازی مشترک Providerها
 *
 * - بررسی تکمیل بودن اطلاعات اتصال (فیلدهای required)
 * - کف/سقف مبلغ از تنظیمات (min_amount / max_amount)
 * - کش توکن OAuth به ازای هر درگاه
 * - تبدیل تومان به ریال
 */
abstract class AbstractGatewayProvider implements GatewayProvider
{
    /** کلیدهای پارامتر بازگشتی که در لاگ ثبت می‌شوند */
    protected array $callbackKeys = [];

    public function isConfigured(PaymentGateway $gateway): bool
    {
        foreach ($this->credentialFields() as $key => $field) {
            if (($field['required'] ?? false) && blank($gateway->credential($key))) {
                return false;
            }
        }

        foreach ($this->settingFields() as $key => $field) {
            if (($field['required'] ?? false) && blank($gateway->setting($key, $field['default'] ?? null))) {
                return false;
            }
        }

        return true;
    }

    public function unavailableReason(PaymentGateway $gateway, Order $order): ?string
    {
        $amount = (int) $order->total_amount;
        $min = (int) $gateway->setting('min_amount', 0);
        $max = (int) $gateway->setting('max_amount', 0);

        if ($min > 0 && $amount < $min) {
            return 'حداقل مبلغ برای ' . $gateway->title . ' ' . number_format($min) . ' تومان است.';
        }

        if ($max > 0 && $amount > $max) {
            return 'حداکثر مبلغ برای ' . $gateway->title . ' ' . number_format($max) . ' تومان است.';
        }

        return null;
    }

    public function callbackSummary(array $callback): array
    {
        return array_intersect_key($callback, array_flip($this->callbackKeys));
    }

    /** فیلدهای مشترک کف/سقف مبلغ (تومان) */
    protected function amountLimitFields(int $min = 0, int $max = 0): array
    {
        return [
            'min_amount' => [
                'label' => 'حداقل مبلغ سفارش (تومان)',
                'type' => 'number',
                'default' => $min,
                'help' => '۰ = بدون محدودیت',
            ],
            'max_amount' => [
                'label' => 'حداکثر مبلغ سفارش (تومان)',
                'type' => 'number',
                'default' => $max,
                'help' => '۰ = بدون محدودیت',
            ],
        ];
    }

    protected function http(): PendingRequest
    {
        return Http::timeout((int) config('payments.http_timeout', 20))->acceptJson();
    }

    protected function rial(int $toman): int
    {
        return $toman * 10;
    }

    protected function baseUrl(PaymentGateway $gateway): string
    {
        return rtrim((string) $gateway->setting('base_url'), '/');
    }

    /**
     * توکن OAuth کش‌شده (کلید به اطلاعات اتصال وابسته است؛ تغییر اطلاعات => توکن جدید)
     *
     * @param  callable(): array{0: ?string, 1: int}  $issue  [توکن، اعتبار به ثانیه]
     */
    protected function cachedToken(PaymentGateway $gateway, callable $issue): ?string
    {
        $key = 'payment-gateway-token:' . $gateway->id . ':' . md5(json_encode([$gateway->credentials, $this->baseUrl($gateway)]));

        if ($token = Cache::get($key)) {
            return $token;
        }

        [$token, $ttl] = $issue();

        if ($token) {
            // کمی زودتر از انقضای واقعی منقضی شود
            Cache::put($key, $token, max(60, $ttl - 60));
        }

        return $token;
    }

    /** شماره موبایل به فرمت 98912xxxxxxx */
    protected function internationalMobile(?string $mobile): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $mobile);

        if ($digits === '') {
            return null;
        }

        return '98' . substr(ltrim(preg_replace('/^(0098|98)/', '', $digits), '0'), 0, 10);
    }

    protected function description(PaymentGateway $gateway, array $context, $fallback): string
    {
        return str_replace(
            ':order',
            (string) ($context['order_number'] ?? $fallback),
            (string) $gateway->setting('description', 'پرداخت سفارش :order')
        );
    }
}
