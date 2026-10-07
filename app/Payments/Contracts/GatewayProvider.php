<?php

namespace App\Payments\Contracts;

use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentGateway;
use App\Payments\Gateways\GatewayRequest;
use App\Payments\Gateways\GatewayVerification;

/**
 * ارائه‌دهنده درگاه بانکی (زرین‌پال، اسنپ‌پی، دیجی‌پی، ...)
 *
 * افزودن درگاه جدید: یک کلاس که این Interface را پیاده کند (ترجیحاً با ارث‌بری از AbstractGatewayProvider)
 * + ثبت در config/payments.php (providers).
 * فرم تنظیمات پنل از credentialFields()/settingFields() ساخته می‌شود؛ نیازی به تغییر UI نیست.
 */
interface GatewayProvider
{
    public function key(): string;

    public function label(): string;

    /**
     * فیلدهای محرمانه (رمزنگاری می‌شوند)
     *
     * @return array<string, array{label: string, type?: string, required?: bool, help?: string}>
     */
    public function credentialFields(): array;

    /**
     * تنظیمات غیرمحرمانه (مثل حالت آزمایشی)
     * type: text | url | number | boolean | select (با options)
     *
     * @return array<string, array{label: string, type?: string, help?: string, default?: mixed, required?: bool, options?: array}>
     */
    public function settingFields(): array;

    /** اطلاعات اتصال لازم وارد شده است؟ (درگاه تنظیم‌نشده قابل فعال‌سازی/استفاده نیست) */
    public function isConfigured(PaymentGateway $gateway): bool;

    /** دلیل غیرقابل‌استفاده بودن درگاه برای این سفارش (مثلاً سقف/کف مبلغ) یا null */
    public function unavailableReason(PaymentGateway $gateway, Order $order): ?string;

    /** ایجاد تراکنش در درگاه و گرفتن آدرس انتقال کاربر */
    public function request(Payment $payment, PaymentGateway $gateway, string $callbackUrl, array $context = []): GatewayRequest;

    /**
     * تأیید پرداخت پس از بازگشت از درگاه
     *
     * @param  array  $callback  پارامترهای بازگشتی درگاه (query string یا فرم POST)
     */
    public function verify(Payment $payment, PaymentGateway $gateway, array $callback): GatewayVerification;

    /** بخش غیرحساس پارامترهای بازگشتی برای ثبت در لاگ پرداخت */
    public function callbackSummary(array $callback): array;
}
