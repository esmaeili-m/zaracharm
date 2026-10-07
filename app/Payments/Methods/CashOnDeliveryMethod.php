<?php

namespace App\Payments\Methods;

use App\Models\Order;
use App\Models\User;
use App\Payments\PaymentResult;
use App\Services\Invoices\InvoiceStockService;
use Illuminate\Support\Facades\DB;

/**
 * پرداخت در محل: سفارش تأیید و ارسال می‌شود؛ پرداخت پس از تحویل توسط ادمین «پرداخت‌شده» ثبت می‌شود
 *
 * تنظیم اختیاری (payment_methods.settings): max_amount = سقف مبلغ سفارش برای این روش
 */
class CashOnDeliveryMethod extends BaseMethod
{
    public function key(): string
    {
        return 'cod';
    }

    public function icon(): string
    {
        return 'M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z';
    }

    public function hint(Order $order, ?User $user): ?string
    {
        return 'نقدی یا با کارت‌خوان هنگام تحویل';
    }

    public function unavailableReason(Order $order, ?User $user): ?string
    {
        $max = (int) $this->setting('max_amount', 0);

        return $max > 0 && (int) $order->total_amount > $max
            ? 'پرداخت در محل برای سفارش‌های بالای ' . number_format($max) . ' تومان امکان‌پذیر نیست.'
            : null;
    }

    public function start(Order $order, array $input = []): PaymentResult
    {
        if ($reason = $this->payableReason($order) ?? $this->unavailableReason($order, $order->user)) {
            return PaymentResult::failed($reason);
        }

        return DB::transaction(function () use ($order) {
            $this->supersedePendingGatewayPayments($order);

            $payment = $this->payments->create($order, 'cod');

            $order->update([
                'payment_method' => 'cod',
                'payment_status' => 'pending',
                'status' => 'processing',
                'expires_at' => null,
            ]);

            // سفارش تأیید شد => موجودی قطعی شود
            app(InvoiceStockService::class)->syncOrder($order);

            return PaymentResult::pending($payment, 'سفارش شما ثبت شد؛ مبلغ هنگام تحویل دریافت می‌شود.');
        });
    }
}
