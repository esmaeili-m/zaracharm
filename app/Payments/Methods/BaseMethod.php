<?php

namespace App\Payments\Methods;

use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\User;
use App\Payments\Contracts\PaymentMethodDriver;
use App\Payments\PaymentService;

abstract class BaseMethod implements PaymentMethodDriver
{
    public function __construct(protected PaymentService $payments)
    {
    }

    public function hint(Order $order, ?User $user): ?string
    {
        return null;
    }

    public function unavailableReason(Order $order, ?User $user): ?string
    {
        return null;
    }

    /** تنظیمات اختصاصی این روش از پنل (payment_methods.settings) */
    protected function setting(string $key, $default = null)
    {
        return (PaymentMethod::where('key', $this->key())->first()?->settings ?? [])[$key] ?? $default;
    }

    /**
     * سفارش باید در انتظار پرداخت باشد؛ پرداخت در حال بررسی مانع پرداخت دوباره است
     */
    protected function payableReason(Order $order): ?string
    {
        if ($order->payment_status === 'paid') {
            return 'این سفارش قبلاً پرداخت شده است.';
        }

        if ($order->status !== 'pending') {
            return 'این سفارش دیگر قابل پرداخت نیست.';
        }

        if ($order->expires_at && $order->expires_at->isPast()) {
            return 'مهلت پرداخت این سفارش به پایان رسیده است.';
        }

        $underReview = Payment::where('order_id', $order->id)
            ->whereIn('method', ['transfer', 'cod'])
            ->where('status', PaymentStatus::Pending->value)
            ->exists();

        return $underReview ? 'پرداخت دیگری برای این سفارش در حال بررسی است.' : null;
    }

    /**
     * پرداخت‌های درگاهِ نیمه‌کاره قبلی همین سفارش لغو می‌شوند (کاربر روش/تلاش جدید را شروع کرده)
     */
    protected function supersedePendingGatewayPayments(Order $order): void
    {
        Payment::where('order_id', $order->id)
            ->where('method', 'gateway')
            ->where('status', PaymentStatus::Pending->value)
            ->pluck('id')
            ->each(fn ($id) => $this->payments->cancel($id, 'تلاش پرداخت جدید ثبت شد.'));
    }
}
