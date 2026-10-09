<?php

namespace App\Payments;

use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentLog;
use App\Services\Invoices\InvoiceStockService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * هسته وضعیت پرداخت‌ها
 *
 * همه تغییر وضعیت‌ها از اینجا انجام می‌شود:
 *  - قفل ردیف پرداخت/سفارش (جلوگیری از تأیید هم‌زمان/چندباره)
 *  - idempotent: تأیید دوباره یک پرداخت پرداخت‌شده اثری ندارد
 *  - ثبت لاگ برای هر تغییر مهم
 */
class PaymentService
{
    // انتقال‌های مجاز وضعیت
    public const TRANSITIONS = [
        'pending' => ['paid', 'failed', 'cancelled', 'rejected'],
        // تأیید دیرهنگام درگاه بعد از خطای ارتباطی
        'failed' => ['paid'],
        'paid' => ['refunded'],
        'cancelled' => [],
        'rejected' => [],
        'refunded' => [],
    ];

    public function create(Order $order, string $method, array $attributes = []): Payment
    {
        $payment = Payment::create(array_merge([
            'order_id' => $order->id,
            'user_id' => $order->user_id,
            'method' => $method,
            'amount' => (int) $order->total_amount,
            'status' => PaymentStatus::Pending->value,
        ], $attributes));

        $this->log($payment, 'created', null, $payment->status, 'ایجاد پرداخت (' . $method . ')', [
            'amount' => $payment->amount,
        ]);

        return $payment;
    }

    /**
     * ثبت موفقیت پرداخت + تکمیل سفارش (idempotent)
     */
    public function markPaid(Payment|int $payment, array $attributes = [], ?string $message = null): Payment
    {
        return DB::transaction(function () use ($payment, $attributes, $message) {
            $payment = Payment::lockForUpdate()->findOrFail($payment instanceof Payment ? $payment->id : $payment);

            if ($payment->isPaid()) {
                return $payment; // قبلاً تأیید شده
            }

            $this->assertTransition($payment, PaymentStatus::Paid->value);

            $order = Order::lockForUpdate()->findOrFail($payment->order_id);

            // سفارش با پرداخت دیگری پرداخت شده => این پرداخت ثبت می‌شود ولی برای بازگشت وجه علامت می‌خورد
            $duplicate = Payment::where('order_id', $order->id)
                ->where('id', '!=', $payment->id)
                ->where('status', PaymentStatus::Paid->value)
                ->exists();

            $from = $payment->status;
            $payment->update(array_merge($attributes, [
                'status' => PaymentStatus::Paid->value,
                'paid_at' => $payment->paid_at ?? now(),
                'failure_reason' => null,
                'meta' => array_merge((array) $payment->meta, $duplicate ? ['duplicate_order_payment' => true] : []),
            ]));

            $this->log($payment, 'paid', $from, PaymentStatus::Paid->value, $message ?? 'پرداخت تأیید شد.', array_filter([
                'reference' => $payment->reference,
                'duplicate_order_payment' => $duplicate ?: null,
            ]));

            if (! $duplicate) {
                $this->fulfilOrder($order);
            }

            return $payment;
        });
    }

    public function markFailed(Payment|int $payment, string $reason, array $data = []): Payment
    {
        return $this->transition($payment, PaymentStatus::Failed->value, 'failed', $reason, ['failure_reason' => mb_substr($reason, 0, 250)], $data);
    }

    public function cancel(Payment|int $payment, string $reason): Payment
    {
        return $this->transition($payment, PaymentStatus::Cancelled->value, 'cancelled', $reason, ['failure_reason' => mb_substr($reason, 0, 250)]);
    }

    /**
     * تأیید کارت‌به‌کارت / پرداخت در محل توسط ادمین
     */
    public function approve(Payment|int $payment, ?string $note = null): Payment
    {
        return $this->markPaid($payment, [
            'admin_note' => $note,
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
        ], 'تأیید توسط مدیر' . ($note ? ': ' . $note : ''));
    }

    /**
     * رد کارت‌به‌کارت توسط ادمین؛ سفارش دوباره قابل پرداخت می‌شود
     */
    public function reject(Payment|int $payment, string $note): Payment
    {
        return DB::transaction(function () use ($payment, $note) {
            $payment = $this->transition($payment, PaymentStatus::Rejected->value, 'rejected', 'رد توسط مدیر: ' . $note, [
                'admin_note' => $note,
                'failure_reason' => mb_substr($note, 0, 250),
                'reviewed_by' => auth()->id(),
                'reviewed_at' => now(),
            ]);

            $order = Order::lockForUpdate()->find($payment->order_id);

            if ($order && $order->payment_status !== 'paid') {
                $order->update([
                    'payment_status' => 'unpaid',
                    'status' => 'pending',
                    'expires_at' => now()->addHours((int) config('payments.retry_window_hours', 24)),
                ]);
                app(InvoiceStockService::class)->syncOrder($order);
            }

            return $payment;
        });
    }

    /**
     * ثبت بازگشت وجه (بازگشت پول خارج از سیستم انجام می‌شود؛ اینجا فقط وضعیت ثبت می‌شود)
     */
    public function refund(Payment|int $payment, string $note): Payment
    {
        return DB::transaction(function () use ($payment, $note) {
            $payment = $this->transition($payment, PaymentStatus::Refunded->value, 'refunded', 'بازگشت وجه: ' . $note, [
                'admin_note' => $note,
                'reviewed_by' => auth()->id(),
                'reviewed_at' => now(),
            ]);

            $order = Order::lockForUpdate()->find($payment->order_id);
            $order?->update(['payment_status' => 'refunded']);
            $order?->invoice?->update(['status' => 'refunded']);

            return $payment;
        });
    }

    /**
     * تغییر وضعیت عمومی با اعتبارسنجی انتقال و لاگ
     */
    public function transition(Payment|int $payment, string $to, string $event, ?string $message = null, array $attributes = [], array $data = []): Payment
    {
        return DB::transaction(function () use ($payment, $to, $event, $message, $attributes, $data) {
            $payment = Payment::lockForUpdate()->findOrFail($payment instanceof Payment ? $payment->id : $payment);

            if ($payment->status === $to) {
                return $payment; // idempotent
            }

            $this->assertTransition($payment, $to);

            $from = $payment->status;
            $payment->update(array_merge($attributes, ['status' => $to]));
            $this->log($payment, $event, $from, $to, $message, $data);

            return $payment;
        });
    }

    public function allowedTransitions(Payment $payment): array
    {
        return self::TRANSITIONS[$payment->status] ?? [];
    }

    protected function assertTransition(Payment $payment, string $to): void
    {
        if (! in_array($to, self::TRANSITIONS[$payment->status] ?? [], true)) {
            throw ValidationException::withMessages([
                'payment' => 'تغییر وضعیت پرداخت از «' . $payment->status_label . '» به «'
                    . (PaymentStatus::tryFrom($to)?->label() ?? $to) . '» مجاز نیست.',
            ]);
        }
    }

    /**
     * سفارش پرداخت‌شده: وضعیت سفارش/فاکتور + قطعی شدن موجودی
     */
    protected function fulfilOrder(Order $order): void
    {
        $order->update([
            'payment_status' => 'paid',
            'status' => $order->status === 'pending' ? 'processing' : $order->status,
            'expires_at' => null,
        ]);

        $order->invoice?->update(['status' => 'paid', 'paid_at' => now()]);

        app(InvoiceStockService::class)->syncOrder($order);

        // ثبت استفاده از کمپین‌ها (برای سقف استفاده و گزارش عملکرد کمپین)؛ خطای آن نباید پرداخت را متوقف کند
        try {
            app(\App\Services\Campaigns\CampaignUsageRecorder::class)->recordForOrder($order);
        } catch (\Throwable $e) {
            report($e);
        }

        // ثبت استفاده از کد تخفیف (سقف کل و سقف هر کاربر)
        try {
            app(\App\Services\Coupons\CouponUsageRecorder::class)->recordForOrder($order);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public function log(Payment $payment, string $event, ?string $from, ?string $to, ?string $message = null, array $data = []): void
    {
        PaymentLog::create([
            'payment_id' => $payment->id,
            'event' => $event,
            'from_status' => $from,
            'to_status' => $to,
            'message' => $message ? mb_substr($message, 0, 250) : null,
            'data' => $data ?: null,
            'user_id' => auth()->id(),
            'ip' => request()?->ip(),
        ]);
    }
}
