<?php

use Livewire\Component;
use App\Payments\Methods\GatewayMethod;
use App\Payments\PaymentManager;

/*
 * بازگشت از درگاه بانکی
 * تأیید پرداخت سمت سرور انجام می‌شود (مبلغ از دیتابیس، شناسه تراکنش باید با پرداخت مطابقت کند)
 * و کاربر به صفحه نتیجه پرداخت سفارش منتقل می‌شود. فراخوانی تکراری این آدرس اثری ندارد.
 * درگاه‌هایی که با فرم POST برمی‌گردند (اسنپ‌پی، دیجی‌پی) در routes/web.php (payment.callback.post) پردازش می‌شوند.
 */
new class extends Component
{
    public function mount(string $uuid)
    {
        /** @var GatewayMethod $driver */
        $driver = app(PaymentManager::class)->method('gateway');

        $payment = $driver->handleCallbackFor($uuid, request()->query());

        return $this->redirectRoute('order.payment.result', [
            'code' => $payment->order?->order_number,
            'payment' => $payment->uuid,
        ]);
    }
};
?>

<div class="min-h-[50vh] flex items-center justify-center" dir="rtl">
    <p class="text-sm font-bold text-gray-500">در حال بررسی نتیجه پرداخت...</p>
</div>
