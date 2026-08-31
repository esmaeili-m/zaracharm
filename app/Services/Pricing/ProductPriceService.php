<?php

namespace App\Services\Pricing;

use App\Enums\CampaignType;
use App\Enums\CampaignTargetType;
use App\Models\Campaign;
use App\Models\Discount;
use App\Models\ProductVariant;
use App\Enums\DiscountType;
class ProductPriceService
{
    /**
     * محاسبه قیمت نهایی یک Variant
     */
    public function calculate(ProductVariant $variant): array
    {
        $price = (int) ($variant->price ?? 0);

        if ($price <= 0) {
            return $this->result(
                price: 0,
                beforeDiscount: 0
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Discount های معمولی
        |--------------------------------------------------------------------------
        */

        $discounts = $this->getDiscounts($variant);

        /*
        |--------------------------------------------------------------------------
        | Campaign ها
        |--------------------------------------------------------------------------
        */

        $campaignDiscounts = $this->getCampaignDiscounts($variant);

        /*
        |--------------------------------------------------------------------------
        | ترکیب همه تخفیف ها
        |--------------------------------------------------------------------------
        */

        $allDiscounts = array_merge(
            $discounts,
            $campaignDiscounts
        );

        /*
        |--------------------------------------------------------------------------
        | انتخاب بهترین تخفیف
        |--------------------------------------------------------------------------
        */

        $bestDiscount = $this->resolveBestDiscount(
            $allDiscounts,
            $price
        );

        /*
        |--------------------------------------------------------------------------
        | بدون تخفیف
        |--------------------------------------------------------------------------
        */

        if (!$bestDiscount) {
            return $this->result(
                price: $price,
                beforeDiscount: $price
            );
        }

        /*
        |--------------------------------------------------------------------------
        | محاسبه تخفیف
        |--------------------------------------------------------------------------
        */

        $discountAmount = (int) $bestDiscount['amount'];

        $afterDiscount = max(
            0,
            $price - $discountAmount
        );

        return $this->result(
            price: $price,
            beforeDiscount: $price,
            discount: $discountAmount,
            afterDiscount: $afterDiscount,
            discountPercent: $this->calculatePercent(
                $price,
                $discountAmount
            ),
            discountSource: $bestDiscount['source'],

            discountId: $bestDiscount['discount_id'] ?? null,
            campaignId: $bestDiscount['campaign_id'] ?? null,
            campaignType: $bestDiscount['campaign_type'] ?? null,
            campaignPriority: $bestDiscount['campaign_priority'] ?? null,
            targetType: $bestDiscount['target_type'] ?? null,
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Discount های معمولی
    |--------------------------------------------------------------------------
    */

    private function getDiscounts(ProductVariant $variant): array
    {
        $product = $variant->product;

        if (!$product) {
            return [];
        }

        $price = (int) ($variant->price ?? 0);

        $discounts = [];

        /*
        |--------------------------------------------------------------------------
        | Product
        |--------------------------------------------------------------------------
        */

        foreach ($product->discountTargets ?? [] as $target) {

            $discount = $target->discount;

            if (!$this->isValidDiscount($discount, $price)) {
                continue;
            }

            $discounts[] = [
                'discount' => $discount,
                'amount' => $this->calculateDiscount(
                    $price,
                    $discount
                ),

                'source' => 'product',

                'priority' => 3,

                'discount_id' => $discount->id,

                'campaign_id' => null,
                'campaign_type' => null,
                'campaign_priority' => null,

                'target_type' => CampaignTargetType::PRODUCT->value,
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | Brand
        |--------------------------------------------------------------------------
        */

        if ($product->brand) {

            foreach ($product->brand->discountTargets ?? [] as $target) {

                $discount = $target->discount;

                if (!$this->isValidDiscount($discount, $price)) {
                    continue;
                }

                $discounts[] = [
                    'discount' => $discount,
                    'amount' => $this->calculateDiscount(
                        $price,
                        $discount
                    ),

                    'source' => 'brand',

                    'priority' => 1,

                    'discount_id' => $discount->id,

                    'campaign_id' => null,
                    'campaign_type' => null,
                    'campaign_priority' => null,

                    'target_type' => CampaignTargetType::BRAND->value,
                ];
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Categories
        |--------------------------------------------------------------------------
        |
        | محصول می‌تواند چند دسته داشته باشد.
        |
        */

        foreach ($product->categories ?? [] as $category) {

            foreach ($category->discountTargets ?? [] as $target) {

                $discount = $target->discount;

                if (!$this->isValidDiscount($discount, $price)) {
                    continue;
                }

                $discounts[] = [
                    'discount' => $discount,

                    'amount' => $this->calculateDiscount(
                        $price,
                        $discount
                    ),

                    'source' => 'category',

                    'priority' => 2,

                    'discount_id' => $discount->id,

                    'campaign_id' => null,
                    'campaign_type' => null,
                    'campaign_priority' => null,

                    'target_type' => CampaignTargetType::CATEGORY->value,
                ];
            }
        }

        return $discounts;
    }

    /*
    |--------------------------------------------------------------------------
    | بررسی Discount
    |--------------------------------------------------------------------------
    */

    private function isValidDiscount(
        ?Discount $discount,
        int $price
    ): bool {

        if (!$discount) {
            return false;
        }

        if (!$discount->status) {
            return false;
        }

        $now = now();

        /*
        |--------------------------------------------------------------------------
        | Start
        |--------------------------------------------------------------------------
        */

        if (
            $discount->starts_at &&
            $now->lt($discount->starts_at)
        ) {
            return false;
        }

        /*
        |--------------------------------------------------------------------------
        | End
        |--------------------------------------------------------------------------
        */

        if (
            $discount->ends_at &&
            $now->gt($discount->ends_at)
        ) {
            return false;
        }

        /*
        |--------------------------------------------------------------------------
        | Minimum Purchase
        |--------------------------------------------------------------------------
        */

        if (
            $discount->minimum_purchase !== null &&
            $price < (int) $discount->minimum_purchase
        ) {
            return false;
        }

        return true;
    }

    /*
    |--------------------------------------------------------------------------
    | Campaign Discounts
    |--------------------------------------------------------------------------
    */

    private function getCampaignDiscounts(
        ProductVariant $variant
    ): array {

        $product = $variant->product;

        if (!$product) {
            return [];
        }

        $price = (int) ($variant->price ?? 0);

        if ($price <= 0) {
            return [];
        }

        /*
        |--------------------------------------------------------------------------
        | Campaign های فعال
        |--------------------------------------------------------------------------
        */

        $campaigns = Campaign::query()
            ->where('status', 1)
            ->where(function ($query) {
                $query
                    ->whereNull('start_at')
                    ->orWhere('start_at', '<=', now());
            })
            ->where(function ($query) {
                $query
                    ->whereNull('end_at')
                    ->orWhere('end_at', '>=', now());
            })
            ->whereIn('type', [
                CampaignType::Discount->value,
                CampaignType::FlashSale->value,
            ])
            ->with([
                'targets',
                'rewards',
            ])
            ->get();

        $discounts = [];

        foreach ($campaigns as $campaign) {

            /*
            |--------------------------------------------------------------------------
            | آیا Campaign روی این محصول اعمال می‌شود؟
            |--------------------------------------------------------------------------
            */

            $matchedTargets = $this->getMatchedCampaignTargets(
                $campaign,
                $product
            );

            if (empty($matchedTargets)) {
                continue;
            }

            /*
            |--------------------------------------------------------------------------
            | Reward های تخفیفی
            |--------------------------------------------------------------------------
            */

            foreach ($campaign->rewards as $reward) {

                /*
                |--------------------------------------------------------------------------
                | فقط Percent و Fixed
                |--------------------------------------------------------------------------
                */

                if (!in_array($reward->reward_type, [0, 1], true)) {
                    continue;
                }

                $amount = $this->calculateCampaignReward(
                    $price,
                    $reward
                );

                if ($amount <= 0) {
                    continue;
                }

                /*
                |--------------------------------------------------------------------------
                | هر Target
                |--------------------------------------------------------------------------
                */

                foreach ($matchedTargets as $target) {

                    $targetPriority = match (
                    (int) $target->target_type
                    ) {
                        CampaignTargetType::PRODUCT->value => 3,
                        CampaignTargetType::CATEGORY->value => 2,
                        CampaignTargetType::BRAND->value => 1,

                        /*
                        | ALL بالاتر از همه
                        */
                        CampaignTargetType::ALL->value => 4,

                        default => 0,
                    };

                    $discounts[] = [
                        'discount' => null,

                        'amount' => $amount,

                        'source' => 'campaign',

                        /*
                        | برای مقایسه Campaign با Discount
                        */
                        'priority' => $targetPriority,

                        /*
                        | اطلاعات Discount
                        */
                        'discount_id' => null,

                        /*
                        | Campaign
                        */
                        'campaign_id' => $campaign->id,

                        'campaign_type' => $campaign->type,

                        'campaign_priority' => (int) $campaign->priority,

                        /*
                        | Target
                        */
                        'target_type' => (int) $target->target_type,
                    ];
                }
            }
        }

        return $discounts;
    }

    /*
    |--------------------------------------------------------------------------
    | پیدا کردن Target های مرتبط با Product
    |--------------------------------------------------------------------------
    */

    private function getMatchedCampaignTargets(
        Campaign $campaign,
                 $product
    ): array {

        $matched = [];

        foreach ($campaign->targets as $target) {

            $targetType = (int) $target->target_type;

            /*
            |--------------------------------------------------------------------------
            | ALL
            |--------------------------------------------------------------------------
            */

            if (
                $targetType ===
                CampaignTargetType::ALL->value
            ) {
                $matched[] = $target;

                continue;
            }

            /*
            |--------------------------------------------------------------------------
            | Product
            |--------------------------------------------------------------------------
            */

            if (
                $targetType ===
                CampaignTargetType::PRODUCT->value
            ) {

                if (
                    (int) $target->target_id ===
                    (int) $product->id
                ) {
                    $matched[] = $target;
                }

                continue;
            }

            /*
            |--------------------------------------------------------------------------
            | Brand
            |--------------------------------------------------------------------------
            */

            if (
                $targetType ===
                CampaignTargetType::BRAND->value
            ) {

                if (
                    $product->brand &&
                    (int) $target->target_id ===
                    (int) $product->brand->id
                ) {
                    $matched[] = $target;
                }

                continue;
            }

            /*
            |--------------------------------------------------------------------------
            | Category
            |--------------------------------------------------------------------------
            */

            if (
                $targetType ===
                CampaignTargetType::CATEGORY->value
            ) {

                $categoryIds = $product->categories
                    ->pluck('id')
                    ->map(fn ($id) => (int) $id)
                    ->toArray();

                if (
                    in_array(
                        (int) $target->target_id,
                        $categoryIds,
                        true
                    )
                ) {
                    $matched[] = $target;
                }

                continue;
            }

            /*
            |--------------------------------------------------------------------------
            | Collection
            |--------------------------------------------------------------------------
            |
            | فعلاً چون رابطه Collection محصول را نداریم
            | این قسمت را بعداً اضافه می‌کنیم.
            |
            */
        }

        return $matched;
    }

    /*
    |--------------------------------------------------------------------------
    | محاسبه Reward کمپین
    |--------------------------------------------------------------------------
    */

    private function calculateCampaignReward(
        int $price,
            $reward
    ): int {

        /*
        |--------------------------------------------------------------------------
        | Percent
        |--------------------------------------------------------------------------
        */

        if ((int) $reward->reward_type === 0) {

            $amount = (int) round(
                $price * ((float) $reward->value / 100)
            );

            /*
            |--------------------------------------------------------------------------
            | Maximum Discount
            |--------------------------------------------------------------------------
            */

            if ($reward->max_value !== null) {

                $amount = min(
                    $amount,
                    (int) $reward->max_value
                );
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Fixed
        |--------------------------------------------------------------------------
        */

        elseif ((int) $reward->reward_type === 1) {

            $amount = (int) $reward->value;
        }

        else {
            return 0;
        }

        /*
        |--------------------------------------------------------------------------
        | تخفیف نباید بیشتر از قیمت باشد
        |--------------------------------------------------------------------------
        */

        return min(
            max($amount, 0),
            $price
        );
    }

    /*
    |--------------------------------------------------------------------------
    | محاسبه Discount معمولی
    |--------------------------------------------------------------------------
    */

    private function calculateDiscount(
        int $price,
        Discount $discount
    ): int {

        if ($discount->type === DiscountType::Percent) {

            $amount = (int) round(
                $price * ((float) $discount->value / 100)
            );

        } elseif ($discount->type === DiscountType::Fixed) {

            $amount = (int) $discount->value;

        } else {

            return 0;
        }

        /*
        |--------------------------------------------------------------------------
        | Maximum Discount
        |--------------------------------------------------------------------------
        */

        if ($discount->maximum_discount !== null) {

            $amount = min(
                $amount,
                (int) $discount->maximum_discount
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Discount cannot be greater than price
        |--------------------------------------------------------------------------
        */

        return min(
            max($amount, 0),
            $price
        );
    }

    /*
    |--------------------------------------------------------------------------
    | انتخاب بهترین تخفیف
    |--------------------------------------------------------------------------
    */

    private function resolveBestDiscount(
        array $discounts,
        int $price
    ): ?array {

        if (empty($discounts)) {
            return null;
        }

        /*
        |--------------------------------------------------------------------------
        | حذف تخفیف های نامعتبر
        |--------------------------------------------------------------------------
        */

        $discounts = array_filter(
            $discounts,
            fn ($item) =>
                isset($item['amount']) &&
                $item['amount'] > 0
        );

        if (empty($discounts)) {
            return null;
        }

        /*
        |--------------------------------------------------------------------------
        | مرتب سازی
        |--------------------------------------------------------------------------
        |
        | اول:
        | بیشترین مبلغ تخفیف
        |
        | اگر برابر:
        | Campaign priority
        |
        | اگر باز هم برابر:
        | Target priority
        |
        | Product > Category > Brand
        |
        */

        usort(
            $discounts,
            function ($a, $b) {

                /*
                |--------------------------------------------------------------------------
                | 1. مبلغ تخفیف
                |--------------------------------------------------------------------------
                */

                if (
                    $a['amount'] !==
                    $b['amount']
                ) {
                    return
                        $b['amount']
                        <=>
                        $a['amount'];
                }

                /*
                |--------------------------------------------------------------------------
                | 2. Campaign Priority
                |--------------------------------------------------------------------------
                */

                $aCampaignPriority =
                    $a['campaign_priority'] ?? 0;

                $bCampaignPriority =
                    $b['campaign_priority'] ?? 0;

                if (
                    $aCampaignPriority !==
                    $bCampaignPriority
                ) {
                    return
                        $bCampaignPriority
                        <=>
                        $aCampaignPriority;
                }

                /*
                |--------------------------------------------------------------------------
                | 3. Target Priority
                |--------------------------------------------------------------------------
                */

                $aPriority =
                    $a['priority'] ?? 0;

                $bPriority =
                    $b['priority'] ?? 0;

                return
                    $bPriority
                    <=>
                    $aPriority;
            }
        );

        return array_values($discounts)[0];
    }

    /*
    |--------------------------------------------------------------------------
    | درصد تخفیف
    |--------------------------------------------------------------------------
    */

    private function calculatePercent(
        int $price,
        int $discount
    ): int {

        if ($price <= 0 || $discount <= 0) {
            return 0;
        }

        return (int) round(
            ($discount / $price) * 100
        );
    }

    /*
    |--------------------------------------------------------------------------
    | خروجی نهایی
    |--------------------------------------------------------------------------
    */

    private function result(
        int $price,
        int $beforeDiscount,
        int $discount = 0,
        ?int $afterDiscount = null,
        int $discountPercent = 0,
        ?string $discountSource = null,
        ?int $discountId = null,
        ?int $campaignId = null,
        ?int $campaignType = null,
        ?int $campaignPriority = null,
        ?int $targetType = null,
    ): array {

        $afterDiscount ??= $price;

        return [
            /*
            | قیمت اصلی Variant
            */
            'price' => $price,

            /*
            | قیمت قبل تخفیف
            */
            'before_discount' => $beforeDiscount,

            /*
            | مبلغ تخفیف
            */
            'discount' => $discount,

            /*
            | درصد تخفیف
            */
            'discount_percent' => $discountPercent,

            /*
            | قیمت نهایی
            */
            'after_discount' => $afterDiscount,

            /*
            | آیا تخفیف داریم؟
            */
            'has_discount' => $discount > 0,

            /*
            | منبع تخفیف:
            | product
            | category
            | brand
            | campaign
            */
            'discount_source' => $discountSource,

            /*
            | Discount ID
            */
            'discount_id' => $discountId,

            /*
            | Campaign ID
            */
            'campaign_id' => $campaignId,

            /*
            | Campaign Type
            */
            'campaign_type' => $campaignType,

            /*
            | Campaign Priority
            */
            'campaign_priority' => $campaignPriority,

            /*
            | Target Type
            */
            'target_type' => $targetType,
        ];
    }
}
