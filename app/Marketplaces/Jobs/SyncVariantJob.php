<?php

namespace App\Marketplaces\Jobs;

use App\Models\MarketplaceListing;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * همگام‌سازی خودکار موجودی/قیمت/وضعیت یک واریانت در همه مارکت‌پلیس‌های فعال
 *
 * یکتا تا شروع اجرا: تغییرات پشت‌سرهم انبار (فروش، رزرو، اصلاح) در یک اجرا تجمیع می‌شوند
 * و هنگام اجرا آخرین موجودی خوانده می‌شود.
 */
class SyncVariantJob implements ShouldQueue, ShouldBeUniqueUntilProcessing
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $uniqueFor = 600;

    public function __construct(public int $variantId)
    {
    }

    public function uniqueId(): string
    {
        return (string) $this->variantId;
    }

    /** ارسال به صف فقط اگر این واریانت به مارکت‌پلیس فعالی با همگام‌سازی خودکار متصل باشد */
    public static function queue(int $variantId): void
    {
        $linked = MarketplaceListing::query()
            ->where('product_variant_id', $variantId)
            ->where('is_active', true)
            ->whereHas('marketplace', fn ($q) => $q->where('is_active', true)->where('auto_sync', true))
            ->exists();

        if ($linked) {
            static::dispatch($variantId)->delay(now()->addSeconds((int) config('marketplaces.stock_sync_delay', 15)));
        }
    }

    public function handle(): void
    {
        MarketplaceListing::query()
            ->where('product_variant_id', $this->variantId)
            ->where('is_active', true)
            ->whereHas('marketplace', fn ($q) => $q->where('is_active', true)->where('auto_sync', true))
            ->pluck('id')
            ->each(fn ($id) => SyncListingJob::dispatch($id, ['price', 'stock', 'status']));
    }
}
