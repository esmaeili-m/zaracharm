<?php

namespace App\Services\Inventory;

use App\Models\InventoryItem;
use App\Models\StockMovement;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * تنها نقطه‌ی تغییر quantity / reserved_quantity ردیف‌های انبار (به‌جز رزرو اولیه‌ی سبد خرید).
 * هر تغییر با قفل ردیف انجام و در stock_movements ثبت می‌شود.
 *
 * قواعد: quantity >= 0 ، reserved >= 0 ، reserved <= quantity
 * یعنی موجودی قابل فروش (quantity - reserved) هرگز منفی نمی‌شود.
 */
class InventoryService
{
    /**
     * اعمال تغییر علامت‌دار روی یک ردیف انبار
     */
    public function apply(
        int $inventoryItemId,
        int $quantityChange,
        int $reservedChange,
        string $type,
        ?Model $reference = null,
        ?int $referenceLineId = null,
        ?string $note = null,
        ?string $label = null
    ): ?StockMovement {
        if ($quantityChange === 0 && $reservedChange === 0) {
            return null;
        }

        return DB::transaction(function () use ($inventoryItemId, $quantityChange, $reservedChange, $type, $reference, $referenceLineId, $note, $label) {

            $row = InventoryItem::query()->lockForUpdate()->findOrFail($inventoryItemId);

            $quantity = (int) $row->quantity + $quantityChange;
            $reserved = (int) $row->reserved_quantity + $reservedChange;

            if ($quantity < 0 || $reserved < 0 || $reserved > $quantity) {
                $available = (int) $row->quantity - (int) $row->reserved_quantity;

                throw ValidationException::withMessages([
                    'stock' => 'موجودی ' . ($label ? '«' . $label . '» ' : '') . 'در انبار کافی نیست (قابل فروش: ' . max(0, $available) . ').',
                ]);
            }

            $row->forceFill([
                'quantity' => $quantity,
                'reserved_quantity' => $reserved,
            ])->save();

            return StockMovement::create([
                'inventory_item_id' => $row->id,
                'product_variant_id' => $row->product_variant_id,
                'inventory_id' => $row->inventory_id,
                'type' => $type,
                'quantity_change' => $quantityChange,
                'reserved_change' => $reservedChange,
                'quantity_after' => $quantity,
                'reserved_after' => $reserved,
                'reference_type' => $reference?->getMorphClass(),
                'reference_id' => $reference?->getKey(),
                'reference_line_id' => $referenceLineId,
                'user_id' => auth()->id(),
                'note' => $note,
            ]);
        });
    }

    /**
     * ردیف انبار یک واریانت در یک انبار مشخص (در صورت نبود، با موجودی صفر ساخته می‌شود)
     */
    public function rowFor(int $variantId, int $inventoryId): InventoryItem
    {
        return InventoryItem::withTrashed()->firstOrCreate(
            ['inventory_id' => $inventoryId, 'product_variant_id' => $variantId],
            ['quantity' => 0, 'reserved_quantity' => 0, 'minimum_quantity' => 0, 'status' => true]
        );
    }

    /**
     * انبار پیشنهادی برای فروش یک واریانت: ردیف فعال با بیشترین موجودی قابل فروش
     */
    public function suggestInventoryId(int $variantId): ?int
    {
        return InventoryItem::query()
            ->where('product_variant_id', $variantId)
            ->where('status', true)
            ->whereHas('inventory', fn ($q) => $q->where('status', true))
            ->orderByRaw('(quantity - reserved_quantity) DESC')
            ->value('inventory_id');
    }

    /**
     * موجودی قابل فروش یک واریانت در یک انبار
     */
    public function available(int $variantId, int $inventoryId): int
    {
        $row = InventoryItem::where('product_variant_id', $variantId)->where('inventory_id', $inventoryId)->first();

        return $row ? max(0, (int) $row->quantity - (int) $row->reserved_quantity) : 0;
    }

    /**
     * تنظیم دستی موجودی (فرم موجودی محصول در داشبورد) به‌صورت یک حرکت «اصلاح موجودی»
     */
    public function setQuantity(int $variantId, int $inventoryId, int $newQuantity, ?string $note = null): InventoryItem
    {
        return DB::transaction(function () use ($variantId, $inventoryId, $newQuantity, $note) {
            $row = $this->rowFor($variantId, $inventoryId);
            $row = InventoryItem::withTrashed()->lockForUpdate()->findOrFail($row->id);

            if ($newQuantity < (int) $row->reserved_quantity) {
                throw ValidationException::withMessages([
                    'quantity' => 'موجودی نمی‌تواند کمتر از مقدار رزروشده برای سفارش‌های باز (' . $row->reserved_quantity . ') باشد.',
                ]);
            }

            $this->apply($row->id, $newQuantity - (int) $row->quantity, 0, 'adjustment', null, null, $note ?? 'اصلاح دستی موجودی');

            return $row->fresh();
        });
    }
}
