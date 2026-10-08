<?php

namespace App\Services\Campaigns;

use App\Enums\CampaignTargetType;
use App\Models\Campaign;
use App\Models\Discount;
use App\Models\Product;
use App\Models\ProductVariant;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Throwable;

/**
 * محاسبات ویزارد کمپین (بدون ذخیره):
 *  - محصولات مشمول بر اساس هدف‌ها (کل فروشگاه / محصول / دسته / برند)
 *  - مبلغ تخفیف — دقیقاً مطابق ProductPriceService::calculateCampaignReward
 *  - پیش‌نمایش قیمت قبل/بعد، هشدار زیان (کمتر از قیمت خرید) و تداخل با تخفیف/کمپین‌های دیگر
 *
 * قاعده موتور قیمت: تخفیف‌ها جمع نمی‌شوند؛ برای هر کالا فقط «بیشترین» تخفیف اعمال می‌شود.
 */
class CampaignPlanner
{
    /**
     * @param  array{all?: bool, products?: int[], categories?: int[], brands?: int[]}  $targets
     */
    public function productQuery(array $targets): Builder
    {
        $query = Product::query()->where('status', true);

        if (! empty($targets['all'])) {
            return $query;
        }

        $products = array_filter(array_map('intval', $targets['products'] ?? []));
        $categories = array_filter(array_map('intval', $targets['categories'] ?? []));
        $brands = array_filter(array_map('intval', $targets['brands'] ?? []));

        if (! $products && ! $categories && ! $brands) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $q) use ($products, $categories, $brands) {
            if ($products) {
                $q->orWhereIn('id', $products);
            }
            if ($categories) {
                $q->orWhereHas('categories', fn ($c) => $c->whereIn('categories.id', $categories));
            }
            if ($brands) {
                $q->orWhereIn('brand_id', $brands);
            }
        });
    }

    /** هدف‌های ذخیره‌شده یک کمپین به شکل آرایه ویزارد */
    public function targetsOf(Campaign $campaign): array
    {
        $targets = $campaign->targets()->get();

        return [
            'all' => $targets->contains(fn ($t) => (int) $t->target_type === CampaignTargetType::ALL->value),
            'products' => $targets->where('target_type', CampaignTargetType::PRODUCT->value)->pluck('target_id')->map(fn ($id) => (int) $id)->values()->all(),
            'categories' => $targets->where('target_type', CampaignTargetType::CATEGORY->value)->pluck('target_id')->map(fn ($id) => (int) $id)->values()->all(),
            'brands' => $targets->where('target_type', CampaignTargetType::BRAND->value)->pluck('target_id')->map(fn ($id) => (int) $id)->values()->all(),
        ];
    }

    /**
     * مبلغ تخفیف یک قیمت (تومان) — همان فرمول موتور قیمت
     *
     * @param  int  $type  0 = درصدی ، 1 = مبلغ ثابت
     */
    public function discountAmount(int $price, int $type, $value, $cap = null): int
    {
        if ($price <= 0 || ! is_numeric($value) || $value <= 0) {
            return 0;
        }

        $amount = $type === 0
            ? (int) round($price * ((float) $value / 100))
            : (int) $value;

        if ($type === 0 && $cap !== null && $cap !== '' && (int) $cap > 0) {
            $amount = min($amount, (int) $cap);
        }

        return max(0, min($amount, $price));
    }

    /**
     * پیش‌نمایش اثر تخفیف روی تنوع‌های محصولات مشمول
     *
     * @return array{rows: Collection, stats: array}
     */
    public function preview(array $targets, int $type, $value, $cap, array $limits = [], ?int $campaignId = null, int $rowLimit = 40): array
    {
        $minItem = (int) ($limits['min_item_price'] ?? 0);
        $maxItem = (int) ($limits['max_item_price'] ?? 0);

        $variants = ProductVariant::query()
            ->where('status', true)
            ->whereIn('product_id', $this->productQuery($targets)->select('id'))
            ->whereNotNull('price')
            ->where('price', '>', 0)
            ->with(['product:id,title,slug', 'values'])
            ->orderBy('product_id')
            ->orderBy('id')
            ->limit(2000)
            ->get();

        $stats = ['variants' => $variants->count(), 'applied' => 0, 'loss' => 0, 'free' => 0, 'overridden' => 0, 'excluded' => 0, 'total_discount' => 0];
        $rows = collect();

        foreach ($variants as $variant) {
            $price = (int) $variant->price;
            $eligible = (! $minItem || $price >= $minItem) && (! $maxItem || $price <= $maxItem);
            $amount = $eligible ? $this->discountAmount($price, $type, $value, $cap) : 0;
            $final = $price - $amount;

            // تخفیف فعلی دیگر (بدون همین کمپین): بزرگ‌تر بودن آن یعنی این کمپین روی این کالا اعمال نمی‌شود
            $competitor = $this->competingDiscount($variant, $campaignId);
            $overridden = $amount > 0 && $competitor && $competitor['amount'] >= $amount;

            $cost = $variant->cost_price ? (int) $variant->cost_price : null;
            $loss = $amount > 0 && ! $overridden && $cost !== null && $final < $cost;

            $stats['applied'] += $amount > 0 && ! $overridden ? 1 : 0;
            $stats['loss'] += $loss ? 1 : 0;
            $stats['free'] += $amount > 0 && $final === 0 ? 1 : 0;
            $stats['overridden'] += $overridden ? 1 : 0;
            $stats['excluded'] += $eligible ? 0 : 1;
            $stats['total_discount'] += $amount > 0 && ! $overridden ? $amount : 0;

            if ($rows->count() < $rowLimit) {
                $rows->push([
                    'variant_id' => $variant->id,
                    'product' => $variant->product?->title,
                    'variant' => $variant->values->pluck('title')->implode(' / '),
                    'price' => $price,
                    'amount' => $amount,
                    'percent' => $price > 0 ? round($amount / $price * 100, 1) : 0,
                    'final' => $final,
                    'cost' => $cost,
                    'loss' => $loss,
                    'eligible' => $eligible,
                    'overridden' => $overridden,
                    'competitor' => $competitor,
                ]);
            }
        }

        return ['rows' => $rows, 'stats' => $stats];
    }

    /** بزرگ‌ترین تخفیف فعلی کالا که از منبع دیگری است (تخفیف یا کمپین دیگر) */
    protected function competingDiscount(ProductVariant $variant, ?int $campaignId): ?array
    {
        try {
            $data = $variant->priceData();
        } catch (Throwable) {
            return null;
        }

        if (empty($data['has_discount']) || (int) ($data['discount'] ?? 0) <= 0) {
            return null;
        }

        if ($campaignId && (int) ($data['campaign_id'] ?? 0) === $campaignId) {
            return null; // تخفیف فعلی همین کمپین است
        }

        $title = match ($data['discount_source'] ?? null) {
            'campaign' => 'کمپین «' . (Campaign::whereKey($data['campaign_id'] ?? 0)->value('title') ?? '؟') . '»',
            default => 'تخفیف «' . (Discount::whereKey($data['discount_id'] ?? 0)->value('title') ?? '؟') . '»',
        };

        return ['amount' => (int) $data['discount'], 'title' => $title];
    }

    /**
     * کمپین‌های فعال/زمان‌بندی‌شده دیگری که بازه زمانی و محصولاتشان با این کمپین هم‌پوشانی دارد
     *
     * @return Collection<int, array{campaign: Campaign, products: int}>
     */
    public function overlappingCampaigns(array $targets, ?Carbon $start, ?Carbon $end, ?int $campaignId = null): Collection
    {
        $mine = $this->productQuery($targets)->pluck('id');

        if ($mine->isEmpty()) {
            return collect();
        }

        return Campaign::query()
            ->where('status', Campaign::STATUS_ACTIVE)
            ->whereIn('type', Campaign::PRICED_TYPES)
            ->when($campaignId, fn ($q) => $q->where('id', '!=', $campaignId))
            ->where(fn ($q) => $q->whereNull('end_at')->orWhere('end_at', '>=', $start ?? now()))
            ->when($end, fn ($q) => $q->where(fn ($q) => $q->whereNull('start_at')->orWhere('start_at', '<=', $end)))
            ->get()
            ->map(function (Campaign $other) use ($mine) {
                $shared = $this->productQuery($this->targetsOf($other))->whereIn('id', $mine)->count();

                return $shared ? ['campaign' => $other, 'products' => $shared] : null;
            })
            ->filter()
            ->values();
    }

    /** متن کوتاه تخفیف: «۲۰٪ (حداکثر ۵۰۰٬۰۰۰ تومان)» یا «۱۰۰٬۰۰۰ تومان» */
    public function rewardText(int $type, $value, $cap = null): string
    {
        if (! is_numeric($value) || $value <= 0) {
            return '—';
        }

        if ($type === 0) {
            $text = rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.') . '٪';

            return $cap ? $text . ' (حداکثر ' . number_format((int) $cap) . ' تومان)' : $text;
        }

        return number_format((int) $value) . ' تومان';
    }
}
