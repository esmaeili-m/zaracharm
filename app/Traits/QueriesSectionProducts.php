<?php

namespace App\Traits;

use App\Models\Product;

/**
 * کوئری مشترک محصولات در سکشن‌های صفحه‌ساز (latest / sales / views / random / manual)
 * کامپوننت می‌تواند با بازنویسی scopeSectionProducts() کوئری را محدود کند (مثلاً به یک برند).
 */
trait QueriesSectionProducts
{
    protected function scopeSectionProducts($query)
    {
        return $query;
    }

    /**
     * کوئری پایه با تمام روابطی که کارت محصول استفاده می‌کند تا N+1 Query نداشته باشیم.
     */
    protected function sectionProductsQuery()
    {
        return $this->scopeSectionProducts(Product::query())
            ->has('variants')
            ->with([
                'categories',
                'brand',
                'media',
                'cheapestVariant',
                'displayVariant',
                'specifications' => fn ($q) => $q->where('product_specifications.status', true),
            ]);
    }

    protected function bestSellingSectionProducts($limit)
    {
        $productIds = $this->scopeSectionProducts(Product::query())
            ->has('variants')
            ->join('product_variants', 'product_variants.product_id', '=', 'products.id')
            ->join('order_items', 'order_items.variant_id', '=', 'product_variants.id')
            ->select('products.id')
            ->selectRaw('SUM(order_items.quantity) as total_sales')
            ->groupBy('products.id')
            ->orderByDesc('total_sales')
            ->limit($limit)
            ->pluck('id');

        // حفظ ترتیب پرفروش‌ترین‌ها بعد از whereIn
        return $this->sectionProductsQuery()
            ->whereIn('id', $productIds)
            ->get()
            ->sortBy(fn ($product) => $productIds->search($product->id))
            ->values();
    }

    protected function querySectionProducts(?string $mode, $limit, array $manualIds = [])
    {
        return match ($mode) {

            'latest' => $this->sectionProductsQuery()
                ->latest()
                ->limit($limit)
                ->get(),

            'sales' => $this->bestSellingSectionProducts($limit),

            'views' => $this->sectionProductsQuery()
                ->withCount('views')
                ->orderByDesc('views_count')
                ->limit($limit)
                ->get(),

            'random' => $this->sectionProductsQuery()
                ->inRandomOrder()
                ->limit($limit)
                ->get(),

            'manual' => $this->sectionProductsQuery()
                ->whereIn('id', $manualIds)
                ->get(),

            default => collect(),
        };
    }
}
