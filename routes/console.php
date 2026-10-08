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

/*
|--------------------------------------------------------------------------
| همگام‌سازی مارکت‌پلیس‌ها (دیجی‌کالا، باسلام، ...)
|--------------------------------------------------------------------------
| --orders : دریافت سفارش مارکت‌پلیس‌هایی که زمانشان رسیده (فاصله از تنظیمات هر مارکت‌پلیس)
| --stock  : تطبیق دوره‌ای قیمت/موجودی/وضعیت همه اتصال‌ها (فقط موارد تغییرکرده ارسال می‌شوند؛
|            تغییر قیمت ناشی از شروع/پایان تخفیف و کمپین هم با همین اجرا ارسال می‌شود)
| --retry  : تلاش دوباره اتصال‌های ناموفق
| --prune  : حذف لاگ‌های قدیمی
| اجرای خودکار نیازمند `php artisan schedule:run` (cron) و `queue:work` روی سرور است.
*/
Artisan::command('marketplaces:sync {--orders} {--stock} {--retry} {--prune} {--marketplace=}', function () {
    $sync = app(\App\Marketplaces\Services\SyncService::class);
    $all = ! $this->option('orders') && ! $this->option('stock') && ! $this->option('retry') && ! $this->option('prune');

    $marketplaces = \App\Models\Marketplace::active()
        ->where('auto_sync', true)
        ->when($this->option('marketplace'), fn ($q, $provider) => $q->where('provider', $provider))
        ->get()
        ->filter(fn ($m) => $m->isUsable());

    foreach ($marketplaces as $marketplace) {
        if (($all || $this->option('orders')) && $marketplace->supports(\App\Marketplaces\Capability::PULL_ORDERS)) {
            $minutes = max(1, (int) $marketplace->setting('order_pull_minutes', 10));

            if (! $marketplace->last_order_sync_at || $marketplace->last_order_sync_at->lte(now()->subMinutes($minutes))) {
                \App\Marketplaces\Jobs\PullOrdersJob::dispatch($marketplace->id);
                $this->line("دریافت سفارش: {$marketplace->title}");
            }
        }

        if (($all || $this->option('stock')) && array_intersect(\App\Marketplaces\Capability::LISTING_SYNC, $marketplace->driver()->capabilities())) {
            $count = $sync->queueMarketplace($marketplace, ['price', 'stock', 'status']);
            $this->line("تطبیق قیمت/موجودی {$marketplace->title}: {$count} اتصال");
        }
    }

    if ($all || $this->option('retry')) {
        \App\Models\MarketplaceListing::query()
            ->where('sync_status', 'failed')
            ->where('is_active', true)
            ->where('failed_attempts', '<', 10)
            ->where('updated_at', '<', now()->subMinutes(30))
            ->whereIn('marketplace_id', $marketplaces->pluck('id'))
            ->pluck('id')
            ->each(fn ($id) => \App\Marketplaces\Jobs\SyncListingJob::dispatch($id));
    }

    if ($all || $this->option('prune')) {
        $deleted = \App\Models\MarketplaceSyncLog::where('created_at', '<', now()->subDays((int) config('marketplaces.log_retention_days', 60)))->delete();
        $this->line("لاگ‌های حذف‌شده: {$deleted}");
    }
})->purpose('همگام‌سازی زمان‌بندی‌شده مارکت‌پلیس‌ها (سفارش، قیمت/موجودی، تلاش مجدد)');

\Illuminate\Support\Facades\Schedule::command('marketplaces:sync --orders')->everyMinute()->withoutOverlapping();
\Illuminate\Support\Facades\Schedule::command('marketplaces:sync --stock')->hourly()->withoutOverlapping();
\Illuminate\Support\Facades\Schedule::command('marketplaces:sync --retry')->everyThirtyMinutes()->withoutOverlapping();
\Illuminate\Support\Facades\Schedule::command('marketplaces:sync --prune')->daily();

/*
|--------------------------------------------------------------------------
| تاریخچه قیمت (نمودار قیمت صفحه محصول)
|--------------------------------------------------------------------------
| روزی یک‌بار قیمت پایه و نهایی (با تخفیف/کمپین) همه تنوع‌های فعال ثبت می‌شود.
*/
Artisan::command('prices:snapshot', function () {
    $count = app(\App\Services\Pricing\PriceHistoryRecorder::class)->snapshotAll();
    $this->info("قیمت {$count} تنوع ثبت شد.");
})->purpose('ثبت روزانه تاریخچه قیمت برای نمودار قیمت');

\Illuminate\Support\Facades\Schedule::command('prices:snapshot')->dailyAt('00:10')->withoutOverlapping();
