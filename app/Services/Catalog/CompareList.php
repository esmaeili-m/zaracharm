<?php

namespace App\Services\Catalog;

use App\Models\Product;
use Illuminate\Support\Collection;

/**
 * لیست مقایسه محصولات (در Session؛ برای مهمان و کاربر واردشده، بین صفحات حفظ می‌شود)
 */
class CompareList
{
    public const MAX = 4;

    protected const KEY = 'compare_products';

    /** @return int[] */
    public function ids(): array
    {
        return array_values(array_unique(array_map('intval', (array) session(self::KEY, []))));
    }

    public function count(): int
    {
        return count($this->ids());
    }

    public function has(int $productId): bool
    {
        return in_array($productId, $this->ids(), true);
    }

    /**
     * @return string added | exists | full | invalid
     */
    public function add(int $productId): string
    {
        if ($this->has($productId)) {
            return 'exists';
        }

        if (! Product::active()->whereKey($productId)->exists()) {
            return 'invalid';
        }

        $ids = $this->ids();

        if (count($ids) >= self::MAX) {
            return 'full';
        }

        $ids[] = $productId;
        session([self::KEY => $ids]);

        return 'added';
    }

    public function remove(int $productId): void
    {
        session([self::KEY => array_values(array_diff($this->ids(), [$productId]))]);
    }

    public function clear(): void
    {
        session()->forget(self::KEY);
    }

    /** محصولات فعال به ترتیب افزوده‌شدن (محصول حذف/غیرفعال‌شده خودکار کنار می‌رود) */
    public function products(array $with = []): Collection
    {
        $ids = $this->ids();

        if (! $ids) {
            return collect();
        }

        $products = Product::active()->with($with)->whereIn('id', $ids)->get()->keyBy('id');

        // پاک‌سازی شناسه‌های نامعتبر
        if ($products->count() !== count($ids)) {
            session([self::KEY => array_values(array_intersect($ids, $products->keys()->all()))]);
        }

        return collect($ids)->map(fn ($id) => $products->get($id))->filter()->values();
    }
}
