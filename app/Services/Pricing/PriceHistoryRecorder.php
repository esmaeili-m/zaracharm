<?php

namespace App\Services\Pricing;

use App\Models\PriceHistory;
use App\Models\ProductVariant;
use Throwable;

/**
 * ثبت تاریخچه قیمت (یک ردیف در روز برای هر تنوع؛ آخرین قیمت همان روز نگه داشته می‌شود)
 *
 * final_price از موتور قیمت (priceData) گرفته می‌شود تا اثر تخفیف‌ها و کمپین‌ها هم در نمودار دیده شود.
 */
class PriceHistoryRecorder
{
    public function record(ProductVariant $variant): void
    {
        $price = (int) $variant->price;

        if ($price <= 0 || ! $variant->product_id) {
            return;
        }

        try {
            $final = (int) ($variant->priceData()['after_discount'] ?? $price);
        } catch (Throwable) {
            $final = $price;
        }

        PriceHistory::updateOrCreate(
            ['product_variant_id' => $variant->id, 'recorded_on' => today()->toDateString()],
            ['product_id' => $variant->product_id, 'price' => $price, 'final_price' => max(0, $final)]
        );
    }

    /** snapshot روزانه همه تنوع‌های فعال (تغییر قیمت ناشی از شروع/پایان کمپین و تخفیف) */
    public function snapshotAll(): int
    {
        $count = 0;

        ProductVariant::query()
            ->where('status', true)
            ->where('price', '>', 0)
            ->whereHas('product', fn ($q) => $q->where('status', true))
            ->with('product')
            ->chunkById(200, function ($variants) use (&$count) {
                foreach ($variants as $variant) {
                    $this->record($variant);
                    $count++;
                }
            });

        return $count;
    }
}
