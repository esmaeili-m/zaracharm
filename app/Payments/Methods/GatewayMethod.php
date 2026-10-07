<?php

namespace App\Payments\Methods;

use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Payments\PaymentManager;
use App\Payments\PaymentResult;
use App\Payments\PaymentService;
use Illuminate\Support\Facades\DB;

/**
 * پرداخت آنلاین از طریق درگاه بانکی (Provider درگاه از PaymentManager)
 */
class GatewayMethod extends BaseMethod
{
    public function __construct(PaymentService $payments, protected PaymentManager $manager)
    {
        parent::__construct($payments);
    }

    public function key(): string
    {
        return 'gateway';
    }

    public function icon(): string
    {
        return 'M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z';
    }

    public function unavailableReason(Order $order, ?User $user): ?string
    {
        $options = $this->manager->gatewayOptions($order);

        if ($options->isEmpty()) {
            return 'درگاه پرداخت فعالی وجود ندارد.';
        }

        return $options->whereNull('disabled')->isEmpty() ? $options->first()['disabled'] : null;
    }

    public function hint(Order $order, ?User $user): ?string
    {
        $usable = $this->manager->gatewayOptions($order)->whereNull('disabled');

        if ($usable->count() > 1) {
            return 'انتخاب از بین ' . $usable->count() . ' درگاه';
        }

        return $usable->isNotEmpty() ? 'از طریق ' . $usable->first()['gateway']->title : null;
    }

    public function start(Order $order, array $input = []): PaymentResult
    {
        if ($reason = $this->payableReason($order)) {
            return PaymentResult::failed($reason);
        }

        // فقط درگاه فعال، تنظیم‌شده و مجاز برای این سفارش
        $gateway = $this->manager->usableGateway($order, isset($input['gateway_id']) ? (int) $input['gateway_id'] : null);

        if (! $gateway) {
            return PaymentResult::failed('درگاه پرداخت انتخاب‌شده در دسترس نیست.');
        }

        $provider = $this->manager->provider($gateway->provider);

        $payment = DB::transaction(function () use ($order, $gateway) {
            $this->supersedePendingGatewayPayments($order);

            $order->update(['payment_method' => 'gateway']);

            return $this->payments->create($order, 'gateway', [
                'gateway' => $gateway->provider,
                'gateway_id' => $gateway->id,
            ]);
        });

        $request = $provider->request($payment, $gateway, route('payment.callback', $payment->uuid), [
            'order' => $order->loadMissing('items'),
            'order_number' => $order->order_number,
            'mobile' => $order->user?->mobile,
            'email' => $order->user?->email,
        ]);

        if (! $request->ok) {
            $this->payments->markFailed($payment, $request->error ?? 'ایجاد تراکنش ناموفق بود.', ['response' => $request->raw]);

            return PaymentResult::failed($request->error ?? 'ایجاد تراکنش در درگاه ناموفق بود.', $payment);
        }

        $payment->update([
            'authority' => $request->authority,
            'gateway_response' => ['request' => $request->raw],
        ]);
        $this->payments->log($payment, 'redirected', $payment->status, $payment->status, 'انتقال به درگاه ' . $gateway->title, [
            'authority' => $request->authority,
        ]);

        return PaymentResult::redirect($payment, $request->redirectUrl);
    }

    /**
     * نقطه ورود مشترک Callback (GET برای زرین‌پال، POST برای اسنپ‌پی/دیجی‌پی)
     * خطای غیرمنتظره ثبت می‌شود و پرداخت در وضعیت فعلی می‌ماند (قابل تأیید مجدد).
     */
    public function handleCallbackFor(string $uuid, array $callback): Payment
    {
        $payment = Payment::where('uuid', $uuid)->where('method', 'gateway')->firstOrFail();

        try {
            return $this->handleCallback($payment, $callback);
        } catch (\Throwable $e) {
            report($e);

            return $payment->fresh();
        }
    }

    /**
     * بازگشت از درگاه: اعتبارسنجی و تأیید (idempotent)
     */
    public function handleCallback(Payment $payment, array $callback): Payment
    {
        // پرداخت قبلاً نهایی شده => فقط نتیجه نمایش داده می‌شود
        if (! $payment->isPending() && $payment->status !== 'failed') {
            return $payment;
        }

        $gateway = $payment->gatewayModel;

        if (! $gateway || ! $this->manager->hasProvider($gateway->provider)) {
            return $this->payments->markFailed($payment, 'درگاه این پرداخت دیگر در دسترس نیست.');
        }

        $provider = $this->manager->provider($gateway->provider);

        $this->payments->log($payment, 'callback', $payment->status, $payment->status, 'بازگشت از درگاه ' . $gateway->title, [
            'query' => $provider->callbackSummary($callback),
        ]);

        $result = $provider->verify($payment, $gateway, $callback);

        $response = array_merge((array) $payment->gateway_response, ['verify' => $result->raw]);

        if (! $result->ok) {
            $payment->update(['gateway_response' => $response]);

            return $payment->status === 'failed'
                ? $payment
                : ($result->cancelled
                    ? $this->payments->cancel($payment, $result->error ?? 'پرداخت لغو شد.')
                    : $this->payments->markFailed($payment, $result->error ?? 'تأیید پرداخت ناموفق بود.'));
        }

        // کد رهگیری تکراری (ثبت‌شده برای پرداخت دیگر) پذیرفته نمی‌شود
        $duplicateRef = Payment::where('method', 'gateway')
            ->where('reference', $result->referenceId)
            ->where('id', '!=', $payment->id)
            ->exists();

        if ($duplicateRef) {
            return $this->payments->markFailed($payment, 'کد رهگیری تکراری است.', ['reference' => $result->referenceId]);
        }

        return $this->payments->markPaid($payment, [
            'reference' => $result->referenceId,
            'transaction_id' => $result->referenceId,
            'card_pan' => $result->cardPan,
            'gateway_response' => $response,
        ], 'پرداخت آنلاین تأیید شد (کد رهگیری ' . $result->referenceId . ').');
    }
}
