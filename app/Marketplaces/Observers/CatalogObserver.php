<?php

namespace App\Marketplaces\Observers;

use App\Marketplaces\Jobs\SyncListingJob;
use App\Marketplaces\Jobs\SyncVariantJob;
use App\Models\InventoryItem;
use App\Models\MarketplaceListing;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Model;
use Throwable;

/**
 * همگام‌سازی خودکار با مارکت‌پلیس‌ها پس از تغییر کاتالوگ/انبار
 *
 * InventoryItem (موجودی/رزرو)       => موجودی واریانت
 * ProductVariant (قیمت/وضعیت/SKU)   => قیمت، موجودی، وضعیت واریانت
 * Product (نام/توضیحات/وضعیت)       => محتوا و وضعیت همه واریانت‌ها
 *
 * ارسال پس از commit تراکنش و از طریق صف انجام می‌شود تا عملیات فروشگاه کند نشود.
 */
class CatalogObserver
{
    public function saved(Model $model): void
    {
        $this->handle($model);
    }

    public function deleted(Model $model): void
    {
        $this->handle($model, true);
    }

    public function restored(Model $model): void
    {
        $this->handle($model, true);
    }

    protected function handle(Model $model, bool $always = false): void
    {
        try {
            match (true) {
                $model instanceof InventoryItem => ($always || $model->wasChanged(['quantity', 'reserved_quantity', 'status']) || $model->wasRecentlyCreated)
                    && $this->afterCommit(fn () => SyncVariantJob::queue((int) $model->product_variant_id)),

                $model instanceof ProductVariant => ($always || $model->wasChanged(['price', 'compare_price', 'status', 'sku']))
                    && $this->afterCommit(fn () => SyncVariantJob::queue((int) $model->id)),

                $model instanceof Product => ($always || $model->wasChanged(['title', 'description', 'short_description', 'status']))
                    && $this->afterCommit(fn () => $this->queueProduct((int) $model->id)),

                default => null,
            };
        } catch (Throwable $e) {
            report($e); // خطای همگام‌سازی نباید ذخیره کاتالوگ را متوقف کند
        }
    }

    protected function queueProduct(int $productId): void
    {
        MarketplaceListing::query()
            ->where('product_id', $productId)
            ->where('is_active', true)
            ->whereHas('marketplace', fn ($q) => $q->where('is_active', true)->where('auto_sync', true))
            ->pluck('id')
            ->each(fn ($id) => SyncListingJob::dispatch($id));
    }

    protected function afterCommit(callable $callback): bool
    {
        app('db')->afterCommit(function () use ($callback) {
            try {
                $callback();
            } catch (Throwable $e) {
                report($e);
            }
        });

        return true;
    }
}
