<?php

namespace App\Marketplaces\Data;

/**
 * نتیجه پردازش وب‌هوک
 *  - orders     : سفارش‌هایی که مستقیم از بدنه وب‌هوک خوانده شده‌اند
 *  - pullOrders : بدنه وب‌هوک معتبر فرض نمی‌شود و سفارش‌ها از API دریافت می‌شوند
 */
final class WebhookResult
{
    /** @param  RemoteOrder[]  $orders */
    public function __construct(
        public readonly array $orders = [],
        public readonly bool $pullOrders = false,
        public readonly ?string $message = null,
    ) {
    }

    public static function pull(?string $message = null): self
    {
        return new self([], true, $message);
    }

    public static function ignored(string $message): self
    {
        return new self([], false, $message);
    }
}
