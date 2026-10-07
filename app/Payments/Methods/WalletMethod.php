<?php

namespace App\Payments\Methods;

use App\Models\Order;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use App\Payments\PaymentResult;
use Illuminate\Support\Facades\DB;

/**
 * پرداخت از موجودی کیف پول (همان لحظه نهایی می‌شود)
 */
class WalletMethod extends BaseMethod
{
    public function key(): string
    {
        return 'wallet';
    }

    public function icon(): string
    {
        return 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z';
    }

    protected function balance(?User $user): int
    {
        return $user ? (int) Wallet::where('user_id', $user->id)->value('balance') : 0;
    }

    public function hint(Order $order, ?User $user): ?string
    {
        return 'موجودی: ' . number_format($this->balance($user)) . ' تومان';
    }

    public function unavailableReason(Order $order, ?User $user): ?string
    {
        return $this->balance($user) < (int) $order->total_amount ? 'موجودی کیف پول کافی نیست.' : null;
    }

    public function start(Order $order, array $input = []): PaymentResult
    {
        if ($reason = $this->payableReason($order)) {
            return PaymentResult::failed($reason);
        }

        return DB::transaction(function () use ($order) {
            $wallet = Wallet::where('user_id', $order->user_id)->lockForUpdate()->first();
            $amount = (int) $order->total_amount;

            if (! $wallet || (int) $wallet->balance < $amount) {
                return PaymentResult::failed('موجودی کیف پول شما کافی نیست.');
            }

            $this->supersedePendingGatewayPayments($order);
            $order->update(['payment_method' => 'wallet']);

            $payment = $this->payments->create($order, 'wallet');

            $wallet->decrement('balance', $amount);

            Transaction::create([
                'user_id' => $order->user_id,
                'type' => 'debit',
                'category' => 'order_payment',
                'amount' => $amount,
                'balance_after' => (int) $wallet->fresh()->balance,
                'status' => 'completed',
                'description' => 'پرداخت سفارش ' . $order->order_number,
                'payment_id' => $payment->id,
                'order_id' => $order->id,
                'reference_type' => $payment->getMorphClass(),
                'reference_id' => $payment->id,
            ]);

            $payment = $this->payments->markPaid($payment, [], 'پرداخت از کیف پول');

            return PaymentResult::paid($payment, 'پرداخت از کیف پول با موفقیت انجام شد.');
        });
    }
}
