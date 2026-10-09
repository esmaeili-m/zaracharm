<?php

namespace App\Services\Accounting;

use App\Models\AccountingEntry;
use App\Models\ProductVariant;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Services\Inventory\InventoryService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * خرید کالا از تأمین‌کننده
 *  - پیش‌نویس: بدون اثر روی انبار، قابل ویرایش
 *  - دریافت:   کالاها از طریق InventoryService به انبار اضافه می‌شوند (حرکت purchase) و
 *              در صورت انتخاب، قیمت خرید واریانت‌ها بروز می‌شود
 *  - لغو:      کالای اضافه‌شده برگشت داده می‌شود (اگر هنوز در انبار موجود باشد)
 *  - پرداخت:   ردیف «پرداخت به تأمین‌کننده» در دفتر حسابداری (از بدهی تأمین‌کننده کم می‌شود)
 */
class PurchaseService
{
    public function __construct(protected InventoryService $inventory)
    {
    }

    /**
     * @param  array{supplier_id: int, inventory_id: int, purchase_date: Carbon, supplier_invoice_number: ?string,
     *               discount_amount: int, update_cost_price: bool, note: ?string, receive: bool,
     *               lines: array<int, array{variant_id: int, quantity: int, unit_cost: int}>}  $data
     */
    public function save(array $data, ?int $purchaseId = null): Purchase
    {
        return DB::transaction(function () use ($data, $purchaseId) {
            $purchase = $purchaseId ? Purchase::lockForUpdate()->findOrFail($purchaseId) : null;

            if ($purchase && $purchase->status !== 'draft') {
                throw ValidationException::withMessages(['lines' => 'فقط خرید پیش‌نویس قابل ویرایش است.']);
            }

            $variants = ProductVariant::withTrashed()->with('product:id,title')
                ->whereIn('id', collect($data['lines'])->pluck('variant_id'))
                ->get()
                ->keyBy('id');

            $items = collect($data['lines'])->map(function ($line) use ($variants) {
                $variant = $variants[$line['variant_id']] ?? null;
                $quantity = max(1, (int) $line['quantity']);
                $unitCost = max(0, (int) $line['unit_cost']);

                return [
                    'variant_id' => $variant?->id,
                    'product_name' => $variant?->product?->title ?? 'کالا',
                    'variant_name' => $variant?->label,
                    'quantity' => $quantity,
                    'unit_cost' => $unitCost,
                    'total_cost' => $quantity * $unitCost,
                ];
            });

            $subtotal = (int) $items->sum('total_cost');
            $discount = min($subtotal, max(0, (int) ($data['discount_amount'] ?? 0)));

            $attributes = [
                'supplier_id' => $data['supplier_id'],
                'inventory_id' => $data['inventory_id'],
                'purchase_date' => $data['purchase_date']->toDateString(),
                'supplier_invoice_number' => $data['supplier_invoice_number'] ?? null,
                'subtotal' => $subtotal,
                'discount_amount' => $discount,
                'total_amount' => $subtotal - $discount,
                'update_cost_price' => (bool) ($data['update_cost_price'] ?? true),
                'note' => $data['note'] ?? null,
            ];

            if ($purchase) {
                $purchase->update($attributes);
                $purchase->items()->delete();
            } else {
                $purchase = Purchase::create($attributes + [
                    'purchase_number' => $this->generateNumber(),
                    'status' => 'draft',
                    'created_by' => auth()->id(),
                ]);
            }

            $purchase->items()->createMany($items->all());

            if (! empty($data['receive'])) {
                $this->receive($purchase->id);
            }

            return $purchase->fresh('items');
        });
    }

    /**
     * دریافت کالا: افزایش موجودی انبار + بروزرسانی قیمت خرید
     */
    public function receive(int $purchaseId): Purchase
    {
        return DB::transaction(function () use ($purchaseId) {
            $purchase = Purchase::with('items')->lockForUpdate()->findOrFail($purchaseId);

            if ($purchase->status !== 'draft') {
                throw ValidationException::withMessages(['purchase' => 'این خرید قبلاً دریافت یا لغو شده است.']);
            }

            if (! $purchase->inventory_id) {
                throw ValidationException::withMessages(['inventory_id' => 'انبار مقصد را انتخاب کنید.']);
            }

            if ($purchase->items->isEmpty()) {
                throw ValidationException::withMessages(['lines' => 'حداقل یک کالا به خرید اضافه کنید.']);
            }

            foreach ($purchase->items as $item) {
                if (! $item->variant_id) {
                    continue; // واریانت حذف شده
                }

                $row = $this->inventory->rowFor($item->variant_id, $purchase->inventory_id);

                $this->inventory->apply(
                    $row->id, (int) $item->quantity, 0, 'purchase', $purchase, $item->id,
                    'ورود کالا از خرید ' . $purchase->purchase_number
                );

                $item->update(['stock_added' => (int) $item->quantity]);

                if ($purchase->update_cost_price && $item->unit_cost > 0) {
                    ProductVariant::withTrashed()->whereKey($item->variant_id)->update(['cost_price' => $item->unit_cost]);
                }
            }

            $purchase->update(['status' => 'received', 'received_at' => now()]);

            return $purchase;
        });
    }

    /**
     * لغو خرید؛ خرید دریافت‌شده کالاهایش را از انبار برمی‌گرداند
     */
    public function cancel(int $purchaseId): Purchase
    {
        return DB::transaction(function () use ($purchaseId) {
            $purchase = Purchase::with('items')->lockForUpdate()->findOrFail($purchaseId);

            if ($purchase->status === 'cancelled') {
                return $purchase;
            }

            if ($purchase->status === 'received') {
                foreach ($purchase->items as $item) {
                    if ((int) $item->stock_added <= 0 || ! $item->variant_id) {
                        continue;
                    }

                    $row = $this->inventory->rowFor($item->variant_id, $purchase->inventory_id);

                    // اگر کالا فروخته شده باشد InventoryService خطای «موجودی کافی نیست» می‌دهد و کل لغو برمی‌گردد
                    $this->inventory->apply(
                        $row->id, -(int) $item->stock_added, 0, 'purchase_reversal', $purchase, $item->id,
                        'لغو خرید ' . $purchase->purchase_number,
                        $item->product_name . ($item->variant_name ? ' - ' . $item->variant_name : '')
                    );

                    $item->update(['stock_added' => 0]);
                }
            }

            $purchase->update(['status' => 'cancelled']);

            return $purchase;
        });
    }

    /**
     * پرداخت به تأمین‌کننده (اختیاری برای یک خرید مشخص)
     */
    public function pay(int $supplierId, int $accountId, int $amount, Carbon $date, ?int $purchaseId = null, ?string $description = null): AccountingEntry
    {
        $supplier = Supplier::withTrashed()->findOrFail($supplierId);
        $purchase = $purchaseId ? Purchase::where('supplier_id', $supplierId)->findOrFail($purchaseId) : null;

        if ($amount <= 0) {
            throw ValidationException::withMessages(['payAmount' => 'مبلغ پرداخت باید بیشتر از صفر باشد.']);
        }

        return AccountingEntry::create([
            'type' => 'expense',
            'source' => 'supplier_payment',
            'account_id' => $accountId,
            'supplier_id' => $supplier->id,
            'purchase_id' => $purchase?->id,
            'amount' => $amount,
            'entry_date' => $date->toDateString(),
            'title' => 'پرداخت به ' . $supplier->title . ($purchase ? ' - خرید ' . $purchase->purchase_number : ''),
            'description' => $description,
            'created_by' => auth()->id(),
        ]);
    }

    /**
     * مبلغ پرداخت‌شده هر خرید: [purchase_id => paid]
     */
    public function paidAmounts(array $purchaseIds): array
    {
        return AccountingEntry::where('source', 'supplier_payment')
            ->whereIn('purchase_id', $purchaseIds)
            ->selectRaw('purchase_id, SUM(amount) as total')
            ->groupBy('purchase_id')
            ->pluck('total', 'purchase_id')
            ->map(fn ($v) => (int) $v)
            ->all();
    }

    protected function generateNumber(): string
    {
        do {
            $number = 'PO-' . now()->format('ymd') . '-' . Str::upper(Str::random(4));
        } while (Purchase::withTrashed()->where('purchase_number', $number)->exists());

        return $number;
    }
}
