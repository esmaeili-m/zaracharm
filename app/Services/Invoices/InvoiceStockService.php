<?php

namespace App\Services\Invoices;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Services\Inventory\InventoryService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * هماهنگی فاکتور با موجودی انبار
 *
 * اصل کار: برای هر قلم فاکتور، «وضعیت هدف» موجودی (چقدر رزرو و چقدر کسر باشد) از روی
 * وضعیت فاکتور/سفارش محاسبه می‌شود و فقط «اختلاف» با مقدار ثبت‌شده‌ی قلم
 * (stock_reserved / stock_deducted) روی انبار اعمال می‌شود. بنابراین:
 *  - اجرای دوباره‌ی هر عملیات هیچ اثری ندارد (idempotent)
 *  - تغییر تعداد فقط مقدار تغییرکرده را اعمال می‌کند
 *  - لغو / حذف / برگشت موجودی را دقیقاً به حالت قبل برمی‌گرداند
 */
class InvoiceStockService
{
    // انتقال‌های مجاز وضعیت
    public const TRANSITIONS = [
        'manual' => [
            'draft' => ['unpaid', 'paid', 'cancelled'],
            'unpaid' => ['paid', 'cancelled'],
            'paid' => ['refunded'],
            'cancelled' => ['draft'],
            'refunded' => [],
        ],
        'online' => [
            'draft' => ['unpaid', 'cancelled'],
            'unpaid' => ['paid', 'cancelled'],
            'paid' => ['refunded'],
            'cancelled' => [],
            'refunded' => [],
        ],
    ];

    // فاکتورهای قابل ویرایش (فقط دستی/حضوری)
    public const EDITABLE_STATUSES = ['draft', 'unpaid', 'paid'];

    public function __construct(protected InventoryService $inventory)
    {
    }

    public function allowedTransitions(Invoice $invoice): array
    {
        $group = $invoice->isOnline() ? 'online' : 'manual';

        return self::TRANSITIONS[$group][$invoice->status] ?? [];
    }

    public function isEditable(Invoice $invoice): bool
    {
        return ! $invoice->isOnline() && ! $invoice->trashed() && in_array($invoice->status, self::EDITABLE_STATUSES, true);
    }

    /*
    |--------------------------------------------------------------------------
    | Reconcile
    |--------------------------------------------------------------------------
    */

    /**
     * همگام‌سازی کامل موجودی یک فاکتور با وضعیت فعلی آن
     */
    public function sync(Invoice|int $invoice): Invoice
    {
        $invoiceId = $invoice instanceof Invoice ? $invoice->id : $invoice;

        return DB::transaction(function () use ($invoiceId) {
            // قفل فاکتور: دو همگام‌سازی هم‌زمان پشت سر هم اجرا می‌شوند
            $invoice = Invoice::withTrashed()->with('items')->lockForUpdate()->findOrFail($invoiceId);
            $order = $invoice->order_id ? Order::lockForUpdate()->find($invoice->order_id) : null;
            $returned = $order ? $this->returnedQuantities($order) : collect();

            foreach ($invoice->items as $item) {
                [$reserve, $deduct] = $this->target($invoice, $order, $item, $returned);
                $this->applyItem($invoice, $item, $reserve, $deduct);
            }

            return $invoice;
        });
    }

    /**
     * همگام‌سازی فاکتور(های) یک سفارش سایت
     */
    public function syncOrder(Order|int $order): void
    {
        $orderId = $order instanceof Order ? $order->id : $order;

        Invoice::withTrashed()->where('order_id', $orderId)->pluck('id')
            ->each(fn ($id) => $this->sync($id));
    }

    /**
     * وضعیت هدف یک قلم: [مقدار رزرو، مقدار کسر]
     */
    protected function target(Invoice $invoice, ?Order $order, InvoiceItem $item, Collection $returned): array
    {
        $qty = (int) $item->quantity;

        if (! $item->variant_id || $invoice->trashed()) {
            return [0, 0];
        }

        // فاکتور دستی / فروش حضوری: صدور = خروج کالا
        if (! $invoice->order_id) {
            return in_array($invoice->status, ['unpaid', 'paid'], true) ? [0, $qty] : [0, 0];
        }

        // فاکتور سفارش سایت
        if (! $order || in_array($invoice->status, ['cancelled', 'refunded'], true) || $order->status === 'cancelled') {
            return [0, 0];
        }

        $isPaid = $invoice->status === 'paid'
            || $order->payment_status === 'paid'
            || in_array($order->status, ['processing', 'shipped', 'completed'], true);

        if ($isPaid) {
            // کالاهای مرجوعی دریافت‌شده به انبار برمی‌گردند
            return [0, max(0, $qty - (int) ($returned[$item->variant_id] ?? 0))];
        }

        $stillReservable = $order->status === 'pending'
            && $order->payment_status !== 'paid'
            && (! $order->expires_at || $order->expires_at->isFuture());

        // سفارش در انتظار پرداخت => رزرو ؛ منقضی‌شده => آزاد
        return $stillReservable ? [$qty, 0] : [0, 0];
    }

    /**
     * اعمال اختلاف وضعیت فعلی و هدف یک قلم روی انبار
     */
    protected function applyItem(Invoice $invoice, InvoiceItem $item, int $targetReserve, int $targetDeduct): void
    {
        $reserveDelta = $targetReserve - (int) $item->stock_reserved;
        $deductDelta = $targetDeduct - (int) $item->stock_deducted;

        if ($reserveDelta === 0 && $deductDelta === 0) {
            return;
        }

        $inventoryId = $item->inventory_id ?: $this->inventory->suggestInventoryId((int) $item->variant_id);

        if (! $inventoryId) {
            throw ValidationException::withMessages([
                'stock' => 'برای «' . $item->product_name . '» هیچ انبار فعالی ثبت نشده است.',
            ]);
        }

        $row = $this->inventory->rowFor((int) $item->variant_id, (int) $inventoryId);

        $type = match (true) {
            $deductDelta > 0 && $reserveDelta < 0 => 'commit',
            $deductDelta > 0 => 'sale',
            $deductDelta < 0 => 'sale_reversal',
            $reserveDelta > 0 => 'reserve',
            default => 'release',
        };

        $this->inventory->apply(
            $row->id,
            -$deductDelta,
            $reserveDelta,
            $type,
            $invoice,
            $item->id,
            'فاکتور ' . $invoice->invoice_number . ' (' . (Invoice::STATUSES[$invoice->status] ?? $invoice->status) . ')',
            $item->product_name
        );

        $item->forceFill([
            'inventory_id' => $inventoryId,
            'stock_reserved' => $targetReserve,
            'stock_deducted' => $targetDeduct,
        ])->save();
    }

    /**
     * تعداد مرجوعی دریافت‌شده‌ی هر واریانت یک سفارش [variant_id => qty]
     */
    protected function returnedQuantities(Order $order): Collection
    {
        // جدول مرجوعی ممکن است هنوز migrate نشده باشد
        static $hasReturns = null;
        $hasReturns ??= Schema::hasTable('return_requests');

        if (! $hasReturns) {
            return collect();
        }

        return DB::table('return_request_items')
            ->join('return_requests', 'return_requests.id', '=', 'return_request_items.return_request_id')
            ->join('order_items', 'order_items.id', '=', 'return_request_items.order_item_id')
            ->where('return_requests.order_id', $order->id)
            ->whereNull('return_requests.deleted_at')
            ->whereIn('return_requests.status', ['received', 'refunded'])
            ->groupBy('order_items.variant_id')
            ->selectRaw('order_items.variant_id, SUM(return_request_items.quantity) as qty')
            ->pluck('qty', 'variant_id');
    }

    /*
    |--------------------------------------------------------------------------
    | Admin operations
    |--------------------------------------------------------------------------
    */

    /**
     * صدور فاکتور دستی / فروش حضوری
     *
     * @param  array  $data  source, status, user_id, customer_name, customer_mobile, note,
     *                       discount_amount, tax_amount, shipping_amount,
     *                       items: [[variant_id, inventory_id, quantity, unit_price], ...]
     */
    public function createManual(array $data): Invoice
    {
        return DB::transaction(function () use ($data) {
            $invoice = Invoice::create([
                'order_id' => null,
                'user_id' => $data['user_id'] ?? null,
                'invoice_number' => $this->generateNumber(),
                'source' => $data['source'] ?? 'manual',
                'customer_name' => $data['customer_name'] ?? null,
                'customer_mobile' => $data['customer_mobile'] ?? null,
                'status' => $data['status'] ?? 'draft',
                'subtotal' => 0,
                'discount_amount' => 0,
                'tax_amount' => 0,
                'shipping_amount' => 0,
                'total_amount' => 0,
                'note' => $data['note'] ?? null,
                'created_by' => auth()->id(),
                'paid_at' => ($data['status'] ?? null) === 'paid' ? now() : null,
            ]);

            foreach ($data['items'] as $line) {
                $invoice->items()->create($this->lineAttributes($line));
            }

            $this->recalculateTotals($invoice, $data);

            return $this->sync($invoice);
        });
    }

    /**
     * ویرایش فاکتور دستی؛ فقط اختلاف تعداد/انبار روی موجودی اعمال می‌شود
     */
    public function updateManual(int $invoiceId, array $data): Invoice
    {
        return DB::transaction(function () use ($invoiceId, $data) {
            $invoice = Invoice::with('items')->lockForUpdate()->findOrFail($invoiceId);

            if (! $this->isEditable($invoice)) {
                throw ValidationException::withMessages(['items' => 'این فاکتور قابل ویرایش نیست.']);
            }

            $existing = $invoice->items->keyBy('id');
            $keptIds = [];

            foreach ($data['items'] as $line) {
                $attributes = $this->lineAttributes($line);
                $item = isset($line['id']) ? $existing->get((int) $line['id']) : null;

                if (! $item) {
                    $invoice->items()->create($attributes);
                    continue;
                }

                $keptIds[] = $item->id;

                // تغییر واریانت یا انبار: اثر قبلی از انبار قبلی برداشته می‌شود
                if ((int) $item->variant_id !== (int) $attributes['variant_id'] || (int) $item->inventory_id !== (int) $attributes['inventory_id']) {
                    $this->applyItem($invoice, $item, 0, 0);
                }

                $item->update($attributes);
            }

            // اقلام حذف‌شده: اول موجودی برگردد، بعد حذف شود
            foreach ($existing->except($keptIds) as $removed) {
                $this->applyItem($invoice, $removed, 0, 0);
                $removed->delete();
            }

            $invoice->update([
                'user_id' => $data['user_id'] ?? null,
                'customer_name' => $data['customer_name'] ?? null,
                'customer_mobile' => $data['customer_mobile'] ?? null,
                'note' => $data['note'] ?? null,
            ]);

            $this->recalculateTotals($invoice, $data);

            return $this->sync($invoice);
        });
    }

    /**
     * تغییر وضعیت فاکتور (و سفارش مرتبط) + همگام‌سازی موجودی
     */
    public function changeStatus(int $invoiceId, string $status): Invoice
    {
        return DB::transaction(function () use ($invoiceId, $status) {
            $invoice = Invoice::lockForUpdate()->findOrFail($invoiceId);

            if ($invoice->status === $status) {
                return $invoice; // idempotent
            }

            if (! in_array($status, $this->allowedTransitions($invoice), true)) {
                throw ValidationException::withMessages([
                    'status' => 'تغییر وضعیت فاکتور از «' . (Invoice::STATUSES[$invoice->status] ?? $invoice->status)
                        . '» به «' . (Invoice::STATUSES[$status] ?? $status) . '» مجاز نیست.',
                ]);
            }

            $invoice->update([
                'status' => $status,
                'paid_at' => $status === 'paid' ? ($invoice->paid_at ?? now()) : $invoice->paid_at,
            ]);

            if ($invoice->order_id) {
                $this->syncOrderStatus($invoice, $status);
            }

            return $this->sync($invoice);
        });
    }

    /**
     * حذف فاکتور دستی: موجودی کسرشده برمی‌گردد، سپس soft delete
     */
    public function delete(int $invoiceId): void
    {
        DB::transaction(function () use ($invoiceId) {
            $invoice = Invoice::lockForUpdate()->findOrFail($invoiceId);

            if ($invoice->isOnline()) {
                throw ValidationException::withMessages(['status' => 'فاکتور سفارش سایت قابل حذف نیست؛ در صورت نیاز آن را لغو کنید.']);
            }

            $invoice->delete();
            $this->sync($invoice->id);
        });
    }

    protected function syncOrderStatus(Invoice $invoice, string $status): void
    {
        $order = Order::lockForUpdate()->find($invoice->order_id);

        if (! $order) {
            return;
        }

        match ($status) {
            'paid' => $order->update([
                'payment_status' => 'paid',
                'status' => $order->status === 'pending' ? 'processing' : $order->status,
            ]),
            'cancelled' => $order->update(['status' => 'cancelled']),
            'refunded' => $order->update(['payment_status' => 'refunded']),
            default => null,
        };
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    protected function lineAttributes(array $line): array
    {
        $variant = ProductVariant::with('product:id,title')->findOrFail((int) $line['variant_id']);
        $quantity = max(1, (int) $line['quantity']);
        $unitPrice = max(0, (int) $line['unit_price']);

        return [
            'variant_id' => $variant->id,
            'inventory_id' => (int) $line['inventory_id'],
            'product_name' => $variant->product?->title ?? 'محصول',
            'variant_name' => $variant->label,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'total_price' => $unitPrice * $quantity,
        ];
    }

    protected function recalculateTotals(Invoice $invoice, array $data): void
    {
        $subtotal = (int) $invoice->items()->sum('total_price');
        $discount = min($subtotal, max(0, (int) ($data['discount_amount'] ?? 0)));
        $tax = max(0, (int) ($data['tax_amount'] ?? 0));
        $shipping = max(0, (int) ($data['shipping_amount'] ?? 0));

        $invoice->update([
            'subtotal' => $subtotal,
            'discount_amount' => $discount,
            'tax_amount' => $tax,
            'shipping_amount' => $shipping,
            'total_amount' => max(0, $subtotal - $discount + $tax + $shipping),
        ]);
    }

    protected function generateNumber(): string
    {
        do {
            $number = 'INV-' . now()->format('YmdHis') . '-' . Str::upper(Str::random(5));
        } while (Invoice::withTrashed()->where('invoice_number', $number)->exists());

        return $number;
    }
}
