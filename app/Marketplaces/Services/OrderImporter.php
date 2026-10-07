<?php

namespace App\Marketplaces\Services;

use App\Marketplaces\Data\RemoteOrder;
use App\Marketplaces\Data\RemoteOrderItem;
use App\Marketplaces\Jobs\SyncVariantJob;
use App\Models\InventoryItem;
use App\Models\Marketplace;
use App\Models\MarketplaceListing;
use App\Models\MarketplaceOrder;
use App\Models\MarketplaceOrderItem;
use App\Models\ProductVariant;
use App\Services\Inventory\InventoryService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * ثبت/به‌روزرسانی سفارش‌های مارکت‌پلیس
 *
 * - یکتایی با (marketplace_id, external_id) + قفل ردیف => سفارش تکراری ثبت نمی‌شود
 * - با ثبت سفارش، موجودی انبار فروشگاه کسر و با لغو/مرجوعی بازگردانده می‌شود (idempotent با stock_status)
 * - پس از تغییر موجودی، موجودی همه مارکت‌پلیس‌های متصل دوباره همگام می‌شود
 */
class OrderImporter
{
    public function __construct(protected InventoryService $inventory)
    {
    }

    /**
     * @return array{0: MarketplaceOrder, 1: bool}  [سفارش، تازه ثبت شد؟]
     */
    public function import(Marketplace $marketplace, RemoteOrder $remote): array
    {
        $affected = [];

        [$order, $created] = DB::transaction(function () use ($marketplace, $remote, &$affected) {
            $order = MarketplaceOrder::where('marketplace_id', $marketplace->id)
                ->where('external_id', $remote->externalId)
                ->lockForUpdate()
                ->first();

            $created = false;

            if (! $order) {
                try {
                    $order = MarketplaceOrder::create($this->attributes($marketplace, $remote) + [
                        'marketplace_id' => $marketplace->id,
                        'external_id' => $remote->externalId,
                    ]);
                    $created = true;
                } catch (UniqueConstraintViolationException) {
                    // ثبت هم‌زمان (وب‌هوک + دریافت دوره‌ای)
                    $order = MarketplaceOrder::where('marketplace_id', $marketplace->id)
                        ->where('external_id', $remote->externalId)
                        ->lockForUpdate()
                        ->firstOrFail();
                }
            }

            if (! $created) {
                $order->update(array_filter($this->attributes($marketplace, $remote), fn ($v) => $v !== null && $v !== ''));
            }

            if (! $order->items()->exists() && $remote->items) {
                $this->createItems($marketplace, $order, $remote->items);
            }

            $affected = $this->syncStock($marketplace, $order->fresh('items'));

            return [$order, $created];
        });

        $this->queueStockSync($affected);

        return [$order->fresh(), $created];
    }

    /**
     * تغییر دستی وضعیت در پنل (مثلاً ثبت لغو سفارش دیجی‌کالا که وب‌هوک آن را ارسال نمی‌کند)
     */
    public function setStatus(MarketplaceOrder $order, string $status, ?string $note = null): MarketplaceOrder
    {
        $affected = [];

        $order = DB::transaction(function () use ($order, $status, $note, &$affected) {
            $order = MarketplaceOrder::lockForUpdate()->findOrFail($order->id);
            $order->update(array_filter(['status' => $status, 'admin_note' => $note], fn ($v) => $v !== null));
            $affected = $this->syncStock($order->marketplace, $order->load('items'), true);

            return $order;
        });

        $this->queueStockSync($affected);

        return $order->fresh();
    }

    /** اعمال/بازگرداندن موجودی بر اساس وضعیت (دستی یا خودکار) */
    public function reconcileStock(MarketplaceOrder $order): MarketplaceOrder
    {
        $affected = DB::transaction(function () use ($order) {
            $order = MarketplaceOrder::lockForUpdate()->findOrFail($order->id);

            return $this->syncStock($order->marketplace, $order->load('items'), true);
        });

        $this->queueStockSync($affected);

        return $order->fresh();
    }

    protected function attributes(Marketplace $marketplace, RemoteOrder $remote): array
    {
        $total = $remote->totalAmount ?? ($remote->itemsAmount + $remote->shippingAmount);

        return [
            'external_order_id' => $remote->externalOrderId,
            'external_status' => $remote->externalStatus ? mb_substr($remote->externalStatus, 0, 100) : null,
            'status' => $remote->status,
            'payment_status' => $remote->paymentStatus,
            'customer_name' => $remote->customerName,
            'customer_mobile' => $remote->customerMobile,
            'province' => $remote->province,
            'city' => $remote->city,
            'address' => $remote->address,
            'postal_code' => $remote->postalCode,
            'items_amount' => $remote->itemsAmount,
            'shipping_amount' => $remote->shippingAmount,
            'total_amount' => $total,
            'tracking_code' => $remote->trackingCode,
            'ordered_at' => $remote->orderedAt,
            'paid_at' => $remote->paidAt,
            'synced_at' => now(),
            'payload' => $remote->payload ?: null,
        ];
    }

    /** @param  RemoteOrderItem[]  $items */
    protected function createItems(Marketplace $marketplace, MarketplaceOrder $order, array $items): void
    {
        foreach ($items as $item) {
            $listing = $this->findListing($marketplace, $item);
            $variantId = $listing?->product_variant_id
                ?? ($item->sku ? ProductVariant::where('sku', $item->sku)->value('id') : null);

            MarketplaceOrderItem::create([
                'marketplace_order_id' => $order->id,
                'marketplace_listing_id' => $listing?->id,
                'product_variant_id' => $variantId,
                'external_item_id' => $item->externalItemId,
                'external_product_id' => $item->externalProductId,
                'external_variant_id' => $item->externalVariantId,
                'title' => mb_substr($item->title, 0, 190),
                'quantity' => $item->quantity,
                'price' => $item->price,
                'total' => $item->price * $item->quantity,
            ]);
        }

        if (! $order->items_amount) {
            $order->update(['items_amount' => collect($items)->sum(fn ($i) => $i->price * $i->quantity)]);
        }
    }

    protected function findListing(Marketplace $marketplace, RemoteOrderItem $item): ?MarketplaceListing
    {
        $query = MarketplaceListing::where('marketplace_id', $marketplace->id);

        if ($item->externalVariantId) {
            $byVariant = (clone $query)->where('external_id', $item->externalProductId)->where('external_variant_id', $item->externalVariantId)->first();
            if ($byVariant) {
                return $byVariant;
            }
        }

        if ($item->externalProductId) {
            $byProduct = (clone $query)->where('external_id', $item->externalProductId)->orderByRaw('external_variant_id IS NULL DESC')->first();
            if ($byProduct) {
                return $byProduct;
            }
        }

        return $item->sku ? (clone $query)->where('external_sku', $item->sku)->first() : null;
    }

    /**
     * کسر موجودی برای سفارش فعال / بازگرداندن برای لغو و مرجوعی
     *
     * @return int[] واریانت‌های تغییرکرده
     */
    protected function syncStock(Marketplace $marketplace, MarketplaceOrder $order, bool $manual = false): array
    {
        if (! $manual && ! $marketplace->setting('deduct_stock_on_order', true)) {
            return [];
        }

        if (in_array($order->status, MarketplaceOrder::SOLD_STATUSES, true) && in_array($order->stock_status, ['pending', 'reverted'], true)) {
            return $this->deduct($order);
        }

        if (in_array($order->status, MarketplaceOrder::RELEASED_STATUSES, true) && in_array($order->stock_status, ['applied', 'partial'], true)) {
            return $this->restore($order);
        }

        return [];
    }

    /** @return int[] */
    protected function deduct(MarketplaceOrder $order): array
    {
        $affected = [];
        $mapped = 0;
        $short = false;
        $label = 'سفارش ' . $order->marketplace->title . ' ' . $order->external_id;

        foreach ($order->items as $item) {
            if (! $item->product_variant_id || $item->stock_deducted >= $item->quantity) {
                continue;
            }

            $mapped++;
            $needed = $item->quantity - $item->stock_deducted;
            $allocations = (array) $item->allocations;

            $rows = InventoryItem::query()
                ->where('product_variant_id', $item->product_variant_id)
                ->where('status', true)
                ->whereHas('inventory', fn ($q) => $q->where('status', true))
                ->orderByRaw('(quantity - reserved_quantity) DESC')
                ->get();

            foreach ($rows as $row) {
                $take = min($needed, max(0, (int) $row->quantity - (int) $row->reserved_quantity));

                if ($take <= 0) {
                    continue;
                }

                try {
                    $this->inventory->apply($row->id, -$take, 0, 'sale', $order, $item->id, $label);
                } catch (Throwable) {
                    continue; // تغییر هم‌زمان موجودی؛ ردیف بعدی
                }

                $allocations[] = ['inventory_item_id' => $row->id, 'quantity' => $take];
                $needed -= $take;

                if ($needed <= 0) {
                    break;
                }
            }

            $item->update([
                'stock_deducted' => $item->quantity - $needed,
                'allocations' => $allocations,
            ]);

            $short = $short || $needed > 0;
            $affected[] = $item->product_variant_id;
        }

        $order->update(['stock_status' => $mapped === 0 ? 'skipped' : ($short ? 'partial' : 'applied')]);

        return array_values(array_unique($affected));
    }

    /** @return int[] */
    protected function restore(MarketplaceOrder $order): array
    {
        $affected = [];
        $label = 'بازگشت سفارش ' . $order->marketplace->title . ' ' . $order->external_id;

        foreach ($order->items as $item) {
            foreach ((array) $item->allocations as $allocation) {
                $this->inventory->apply((int) $allocation['inventory_item_id'], (int) $allocation['quantity'], 0, 'sale_reversal', $order, $item->id, $label);
            }

            if ($item->allocations) {
                $affected[] = $item->product_variant_id;
            }

            $item->update(['stock_deducted' => 0, 'allocations' => null]);
        }

        $order->update(['stock_status' => 'reverted']);

        return array_values(array_unique(array_filter($affected)));
    }

    /** @param  int[]  $variantIds */
    protected function queueStockSync(array $variantIds): void
    {
        foreach (array_unique($variantIds) as $variantId) {
            SyncVariantJob::queue((int) $variantId);
        }
    }
}
