<?php

namespace App\Traits;

use App\Models\Product;
use Illuminate\Support\Facades\Auth;

/**
 * افزودن/حذف علاقه‌مندی از روی کارت محصول در کامپوننت‌های Livewire
 * (از همان جدول wishlists استفاده می‌کند)
 */
trait HandlesWishlist
{
    public array $wishlistIds = [];

    protected function loadWishlistIds(iterable $productIds): void
    {
        if (! Auth::check()) {
            $this->wishlistIds = [];
            return;
        }

        $ids = collect($productIds)->filter()->unique()->values();

        $this->wishlistIds = $ids->isEmpty()
            ? []
            : Auth::user()->wishlists()
                ->whereIn('product_id', $ids)
                ->pluck('product_id')
                ->map(fn ($id) => (int) $id)
                ->all();
    }

    public function isWishlisted($productId): bool
    {
        return in_array((int) $productId, $this->wishlistIds, true);
    }

    public function toggleWishlist(int $productId)
    {
        // مهمان: بعد از ورود به همین صفحه برمی‌گردد
        if (! Auth::check()) {
            session()->put('url.intended', url()->previous());

            return $this->redirectRoute('login');
        }

        if (! Product::active()->whereKey($productId)->exists()) {
            $this->dispatch('alert', type: 'error', message: 'این محصول در دسترس نیست.');
            return;
        }

        $wishlist = Auth::user()->wishlists()
            ->where('product_id', $productId)
            ->first();

        if ($wishlist) {
            $wishlist->delete();
            $this->wishlistIds = array_values(array_diff($this->wishlistIds, [$productId]));

            $this->dispatch('alert', type: 'success', message: 'محصول از علاقه‌مندی‌ها حذف شد.');
            return;
        }

        Auth::user()->wishlists()->createOrFirst([
            'product_id' => $productId,
        ]);
        $this->wishlistIds[] = $productId;

        $this->dispatch('alert', type: 'success', message: 'محصول به علاقه‌مندی‌ها اضافه شد.');
    }
}
