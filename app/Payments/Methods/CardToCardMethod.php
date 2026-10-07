<?php

namespace App\Payments\Methods;

use App\Models\BankCard;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Payments\PaymentResult;
use Illuminate\Support\Facades\DB;

/**
 * کارت‌به‌کارت: مشتری به یکی از کارت‌های فعال واریز و کد رهگیری را ثبت می‌کند؛ ادمین تأیید/رد می‌کند
 *
 * input: bank_card_id, reference (کد رهگیری), payer_name?, payer_card? (۴ رقم آخر), payer_paid_at?
 */
class CardToCardMethod extends BaseMethod
{
    public function key(): string
    {
        return 'transfer';
    }

    public function icon(): string
    {
        return 'M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4';
    }

    public function unavailableReason(Order $order, ?User $user): ?string
    {
        return BankCard::active()->exists() ? null : 'کارت بانکی فعالی ثبت نشده است.';
    }

    public function hint(Order $order, ?User $user): ?string
    {
        return 'واریز و ثبت کد رهگیری';
    }

    // ارقام فارسی/عربی => لاتین، حذف فاصله و خط تیره
    public static function normalizeReference(?string $value): string
    {
        $value = strtr((string) $value, [
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        ]);

        return strtoupper((string) preg_replace('/[\s\-_]+/u', '', $value));
    }

    public function start(Order $order, array $input = []): PaymentResult
    {
        if ($reason = $this->payableReason($order)) {
            return PaymentResult::failed($reason);
        }

        $card = BankCard::active()->find($input['bank_card_id'] ?? 0);

        if (! $card) {
            return PaymentResult::failed('کارت مقصد را انتخاب کنید.');
        }

        $reference = self::normalizeReference($input['reference'] ?? '');

        if ($reference === '' || ! preg_match('/^[A-Z0-9]{4,40}$/', $reference)) {
            return PaymentResult::failed('کد رهگیری معتبر نیست.');
        }

        return DB::transaction(function () use ($order, $card, $reference, $input) {
            // جلوگیری از ثبت دوباره یک کد رهگیری (قید unique دیتابیس هم وجود دارد)
            if (Payment::where('method', 'transfer')->where('reference', $reference)->lockForUpdate()->exists()) {
                return PaymentResult::failed('این کد رهگیری قبلاً ثبت شده است.');
            }

            $this->supersedePendingGatewayPayments($order);

            $payment = $this->payments->create($order, 'transfer', [
                'bank_card_id' => $card->id,
                'reference' => $reference,
                'payer_name' => filled($input['payer_name'] ?? null) ? mb_substr(trim($input['payer_name']), 0, 190) : null,
                'payer_card' => filled($input['payer_card'] ?? null) ? substr(preg_replace('/\D/', '', self::normalizeReference($input['payer_card'])), -4) : null,
                'payer_paid_at' => $input['payer_paid_at'] ?? null,
                'meta' => ['card' => ['bank' => $card->bank_name, 'number' => $card->card_number, 'owner' => $card->owner_name]],
            ]);

            // تا بررسی ادمین سفارش منقضی نمی‌شود و رزرو موجودی حفظ می‌شود
            $order->update([
                'payment_method' => 'transfer',
                'payment_status' => 'pending',
                'expires_at' => null,
            ]);

            return PaymentResult::pending($payment, 'اطلاعات پرداخت ثبت شد و پس از بررسی تأیید خواهد شد.');
        });
    }
}
