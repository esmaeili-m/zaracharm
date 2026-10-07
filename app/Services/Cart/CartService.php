<?php

namespace App\Services\Cart;

use Illuminate\Support\Facades\DB;

/**
 * عملیات مشترک سبد خرید (سبد فعال کاربر، افزودن کالا، تعداد اقلام)
 * هر جا سبد تغییر کند باید رویداد Livewire «cart-updated» دیسپچ شود تا شمارنده‌ها بروز شوند.
 */
class CartService
{
    public const MAX_QTY_PER_ITEM = 20;

    public function activeCartId(int $userId, bool $create = false): ?int
    {
        $cartId = DB::table('carts')
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->value('id');

        if (! $cartId && $create) {
            $cartId = DB::table('carts')->insertGetId([
                'user_id' => $userId,
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return $cartId ? (int) $cartId : null;
    }

    /**
     * تعداد کل کالاهای سبد فعال (جمع تعدادها، نه تعداد ردیف‌ها)
     */
    public function count(?int $userId): int
    {
        if (! $userId) {
            return 0;
        }

        $cartId = $this->activeCartId($userId);

        return $cartId ? (int) DB::table('cart_items')->where('cart_id', $cartId)->sum('quantity') : 0;
    }

    /**
     * موجودی قابل فروش یک واریانت در انبارهای فعال
     */
    public function stock(int $variantId): int
    {
        return (int) DB::table('inventory_items')
            ->join('inventories', 'inventories.id', '=', 'inventory_items.inventory_id')
            ->where('inventory_items.product_variant_id', $variantId)
            ->where('inventory_items.status', 1)
            ->whereNull('inventory_items.deleted_at')
            ->where('inventories.status', 1)
            ->whereNull('inventories.deleted_at')
            ->selectRaw('SUM(inventory_items.quantity - inventory_items.reserved_quantity) as stock')
            ->value('stock');
    }

    /**
     * افزودن کالا به سبد؛ اگر همین واریانت در سبد باشد فقط تعدادش زیاد می‌شود (ردیف تکراری ساخته نمی‌شود)
     *
     * @return array{ok: bool, message: string}
     */
    public function add(int $userId, int $productId, int $variantId, int $unitPrice, int $quantity = 1): array
    {
        return DB::transaction(function () use ($userId, $productId, $variantId, $unitPrice, $quantity) {
            $cartId = $this->activeCartId($userId, true);

            // قفل ردیف‌های سبد تا دو کلیک هم‌زمان ردیف تکراری نسازند
            $rows = DB::table('cart_items')->where('cart_id', $cartId)->lockForUpdate()->get();

            $existing = $rows->first(function ($row) use ($variantId) {
                $attributes = json_decode($row->attributes ?? '{}', true) ?: [];

                return (int) ($row->variant_id ?? 0) === $variantId || (int) ($attributes['variant_id'] ?? 0) === $variantId;
            });

            $inCart = (int) $rows->filter(function ($row) use ($variantId) {
                $attributes = json_decode($row->attributes ?? '{}', true) ?: [];

                return (int) ($row->variant_id ?? 0) === $variantId || (int) ($attributes['variant_id'] ?? 0) === $variantId;
            })->sum('quantity');

            $stock = $this->stock($variantId);

            if ($stock <= 0) {
                return ['ok' => false, 'message' => 'موجودی این کالا تمام شده است.'];
            }

            if ($inCart + $quantity > $stock) {
                return ['ok' => false, 'message' => 'به سقف موجودی این کالا رسیده‌اید.'];
            }

            if ($inCart + $quantity > self::MAX_QTY_PER_ITEM) {
                return ['ok' => false, 'message' => 'حداکثر تعداد قابل سفارش برای این کالا ' . self::MAX_QTY_PER_ITEM . ' عدد است.'];
            }

            if ($existing) {
                DB::table('cart_items')->where('id', $existing->id)->update([
                    'quantity' => (int) $existing->quantity + $quantity,
                    'price' => $unitPrice,
                    'variant_id' => $variantId,
                    'updated_at' => now(),
                ]);

                return ['ok' => true, 'message' => 'تعداد این کالا در سبد خرید افزایش یافت.'];
            }

            DB::table('cart_items')->insert([
                'cart_id' => $cartId,
                'product_id' => $productId,
                'variant_id' => $variantId,
                'quantity' => $quantity,
                'price' => $unitPrice,
                'attributes' => json_encode(['variant_id' => $variantId]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return ['ok' => true, 'message' => 'به سبد خرید اضافه شد.'];
        });
    }
}
