<?php

namespace App\Services\Coupons;

use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\Order;

/**
 * ثبت استفاده از کد تخفیف بعد از پرداخت موفق سفارش
 * (قبلاً هیچ‌جا ثبت نمی‌شد و سقف usage_limit / usage_per_user عملاً اعمال نمی‌شد)
 * idempotent: برای هر سفارش فقط یک‌بار ثبت می‌شود.
 */
class CouponUsageRecorder
{
    public function recordForOrder(Order $order): void
    {
        if (! $order->coupon_id || ! $order->user_id) {
            return;
        }

        if (CouponUsage::where('order_id', $order->id)->where('coupon_id', $order->coupon_id)->exists()) {
            return;
        }

        CouponUsage::create([
            'coupon_id' => $order->coupon_id,
            'user_id' => $order->user_id,
            'order_id' => $order->id,
        ]);

        Coupon::whereKey($order->coupon_id)->increment('used_count');
    }
}
