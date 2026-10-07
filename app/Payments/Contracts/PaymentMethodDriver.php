<?php

namespace App\Payments\Contracts;

use App\Models\Order;
use App\Models\User;
use App\Payments\PaymentResult;

/**
 * یک روش پرداخت (درگاه، کیف پول، کارت‌به‌کارت، پرداخت در محل، ...)
 *
 * افزودن روش جدید: یک کلاس که این Interface را پیاده کند + ثبت در config/payments.php (methods)
 * + یک ردیف در جدول payment_methods (از پنل > تنظیمات پرداخت قابل فعال‌سازی است).
 */
interface PaymentMethodDriver
{
    /** کلید یکتا (همان payment_methods.key و payments.method) */
    public function key(): string;

    /** مسیر SVG آیکون برای Checkout */
    public function icon(): string;

    /**
     * آیا این روش برای این سفارش/کاربر قابل استفاده است؟
     * در صورت عدم امکان، دلیل به‌صورت متن برگردانده می‌شود (null = قابل استفاده)
     */
    public function unavailableReason(Order $order, ?User $user): ?string;

    /** توضیح کوتاه زیر عنوان در Checkout (مثلاً موجودی کیف پول) */
    public function hint(Order $order, ?User $user): ?string;

    /**
     * شروع پرداخت سفارش
     *
     * @param  array  $input  داده‌های اختصاصی روش (مثلاً کد رهگیری کارت‌به‌کارت)
     */
    public function start(Order $order, array $input = []): PaymentResult;
}
