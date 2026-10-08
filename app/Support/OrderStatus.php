<?php

namespace App\Support;

use App\Models\Order;

/**
 * برچسب و رنگ وضعیت سفارش‌های سایت (وضعیت‌ها رشته ساده هستند، نه enum)
 *
 * status         : pending | processing | paid | shipped | delivered | completed | cancelled | returned
 * payment_status : unpaid | pending | paid | refunded
 */
class OrderStatus
{
    public const STATUSES = [
        'pending' => ['title' => 'در انتظار پرداخت', 'color' => 'amber'],
        'paid' => ['title' => 'پرداخت شده', 'color' => 'emerald'],
        'processing' => ['title' => 'در حال آماده‌سازی', 'color' => 'brown'],
        'shipped' => ['title' => 'ارسال شده', 'color' => 'purple'],
        'delivered' => ['title' => 'تحویل شده', 'color' => 'emerald'],
        'completed' => ['title' => 'تحویل شده', 'color' => 'emerald'],
        'cancelled' => ['title' => 'لغو شده', 'color' => 'red'],
        'returned' => ['title' => 'مرجوع شده', 'color' => 'amber'],
    ];

    public const PAYMENT_STATUSES = [
        'unpaid' => 'پرداخت نشده',
        'pending' => 'در انتظار تأیید پرداخت',
        'paid' => 'پرداخت شده',
        'refunded' => 'بازگشت وجه',
    ];

    public const PAYMENT_METHODS = [
        'gateway' => 'درگاه بانکی',
        'wallet' => 'کیف پول',
        'transfer' => 'کارت به کارت',
        'cod' => 'پرداخت در محل',
    ];

    // گروه‌های گزارش پنل کاربر
    public const IN_PROGRESS = ['paid', 'processing', 'shipped'];
    public const DONE = ['delivered', 'completed'];
    public const CANCELLED = ['cancelled'];

    // کلاس‌های کامل Tailwind (برای اسکن build؛ رنگ پویا ساخته نمی‌شود)
    protected const CLASSES = [
        'amber' => ['badge' => 'bg-amber-500/10 border-amber-500/20 text-amber-600 dark:text-amber-400', 'dot' => 'bg-amber-500'],
        'emerald' => ['badge' => 'bg-emerald-500/10 border-emerald-500/20 text-emerald-600 dark:text-emerald-400', 'dot' => 'bg-emerald-500'],
        'brown' => ['badge' => 'bg-brown-500/10 border-brown-500/20 text-brown-600 dark:text-brown-400', 'dot' => 'bg-brown-500'],
        'purple' => ['badge' => 'bg-purple-500/10 border-purple-500/20 text-purple-600 dark:text-purple-400', 'dot' => 'bg-purple-500'],
        'red' => ['badge' => 'bg-red-500/10 border-red-500/20 text-red-600 dark:text-red-400', 'dot' => 'bg-red-500'],
        'gray' => ['badge' => 'bg-gray-500/10 border-gray-500/20 text-gray-600 dark:text-gray-400', 'dot' => 'bg-gray-500'],
    ];

    /** @return array{title: string, class: string, dot: string} */
    public static function badge(?string $status): array
    {
        $definition = self::STATUSES[$status] ?? ['title' => 'نامشخص', 'color' => 'gray'];
        $classes = self::CLASSES[$definition['color']];

        return ['title' => $definition['title'], 'class' => $classes['badge'], 'dot' => $classes['dot']];
    }

    /** همان شرایطی که صفحه checkout برای پرداخت می‌پذیرد */
    public static function isPayable(Order $order): bool
    {
        return $order->status === 'pending'
            && $order->payment_status === 'unpaid'
            && $order->expires_at?->isFuture();
    }

    public static function paymentStatusLabel(?string $status): string
    {
        return self::PAYMENT_STATUSES[$status] ?? 'نامشخص';
    }

    public static function paymentMethodLabel(?string $method): ?string
    {
        return $method ? (self::PAYMENT_METHODS[$method] ?? $method) : null;
    }
}
