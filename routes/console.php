<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| همگام‌سازی رزروهای انبار سفارش‌های سایت
|--------------------------------------------------------------------------
| رزرو سفارش منقضی/لغوشده آزاد و رزرو سفارش پرداخت‌شده قطعی (کسر از انبار) می‌شود.
| idempotent است و اجرای مکرر اثر اضافه ندارد. برای اجرای خودکار، cron مربوط به
| `php artisan schedule:run` باید روی سرور فعال باشد.
*/
Artisan::command('inventory:sync-reservations', function () {
    $service = app(\App\Services\Invoices\InvoiceStockService::class);
    $synced = 0;
    $failed = 0;

    \App\Models\Invoice::withTrashed()
        ->whereNotNull('order_id')
        ->whereHas('items', fn ($q) => $q->where('stock_reserved', '>', 0))
        ->select('id')
        ->chunkById(100, function ($invoices) use ($service, &$synced, &$failed) {
            foreach ($invoices as $invoice) {
                try {
                    $service->sync($invoice->id);
                    $synced++;
                } catch (\Throwable $e) {
                    $failed++;
                    report($e);
                }
            }
        });

    $this->info("فاکتورهای بررسی‌شده: {$synced} | خطا: {$failed}");
})->purpose('آزادسازی رزرو سفارش‌های منقضی و قطعی کردن رزرو سفارش‌های پرداخت‌شده');

\Illuminate\Support\Facades\Schedule::command('inventory:sync-reservations')
    ->everyFiveMinutes()
    ->withoutOverlapping();
