<?php

namespace App\Payments;

use App\Models\Payment;

/**
 * نتیجه شروع پرداخت توسط یک روش
 */
final class PaymentResult
{
    public const REDIRECT = 'redirect';   // انتقال به درگاه
    public const PAID = 'paid';           // پرداخت همان لحظه انجام شد (کیف پول)
    public const PENDING = 'pending';     // ثبت شد و منتظر تأیید است (کارت‌به‌کارت / پرداخت در محل)
    public const FAILED = 'failed';

    private function __construct(
        public readonly string $type,
        public readonly ?Payment $payment = null,
        public readonly ?string $redirectUrl = null,
        public readonly ?string $message = null,
    ) {
    }

    public static function redirect(Payment $payment, string $url): self
    {
        return new self(self::REDIRECT, $payment, $url);
    }

    public static function paid(Payment $payment, ?string $message = null): self
    {
        return new self(self::PAID, $payment, null, $message);
    }

    public static function pending(Payment $payment, ?string $message = null): self
    {
        return new self(self::PENDING, $payment, null, $message);
    }

    public static function failed(string $message, ?Payment $payment = null): self
    {
        return new self(self::FAILED, $payment, null, $message);
    }

    public function isFailed(): bool
    {
        return $this->type === self::FAILED;
    }
}
