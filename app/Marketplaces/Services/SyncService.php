<?php

namespace App\Marketplaces\Services;

use App\Marketplaces\Capability;
use App\Marketplaces\Data\ConnectionResult;
use App\Marketplaces\Data\RemoteListingPage;
use App\Marketplaces\Data\WebhookResult;
use App\Marketplaces\Exceptions\MarketplaceException;
use App\Marketplaces\Jobs\PullOrdersJob;
use App\Marketplaces\Jobs\SyncListingJob;
use App\Marketplaces\MarketplaceManager;
use App\Models\Marketplace;
use App\Models\MarketplaceListing;
use App\Models\MarketplaceOrder;
use Illuminate\Http\Request;
use Throwable;

/**
 * هسته همگام‌سازی مارکت‌پلیس‌ها (مستقل از API هر سرویس)
 */
class SyncService
{
    public function __construct(
        protected MarketplaceManager $manager,
        protected ListingBuilder $builder,
        protected OrderImporter $importer,
    ) {
    }

    // ---------------------------------------------------------------- اتصال

    public function testConnection(Marketplace $marketplace): ConnectionResult
    {
        if (! $marketplace->hasDriver()) {
            return ConnectionResult::failure('Adapter این مارکت‌پلیس تعریف نشده است.');
        }

        if (! $marketplace->isConfigured()) {
            $result = ConnectionResult::failure('اطلاعات اتصال تکمیل نشده است.');
        } else {
            try {
                $result = $marketplace->driver()->testConnection($marketplace, $this->manager->client($marketplace));
            } catch (Throwable $e) {
                report($e);
                $result = ConnectionResult::failure($e->getMessage());
            }
        }

        if ($result->ok && $result->settings) {
            $marketplace->mergeSettings($result->settings);
        }

        $marketplace->forceFill([
            'connection_status' => $result->ok ? 'connected' : 'failed',
            'connection_message' => mb_substr($result->message, 0, 190),
            'connection_checked_at' => now(),
        ])->save();

        $this->manager->client($marketplace)->log('connection', $result->ok ? 'success' : 'failed', $result->message);

        return $result;
    }

    // ---------------------------------------------------------------- محصولات

    /**
     * همگام‌سازی یک اتصال؛ فقط فیلدهای تغییرکرده ارسال می‌شوند (مگر force)
     *
     * @param  string[]|null  $only  content | price | stock | status (null = همه)
     * @return string created | updated | unchanged | skipped
     *
     * @throws MarketplaceException  فقط برای خطاهای قابل تکرار (برای Retry صف)
     */
    public function syncListing(MarketplaceListing $listing, ?array $only = null, bool $force = false, int $attempt = 1): string
    {
        $listing->loadMissing('marketplace', 'variant');
        $marketplace = $listing->marketplace;

        if (! $marketplace || ! $marketplace->isUsable() || ! $listing->is_active) {
            return 'skipped';
        }

        $client = $this->manager->client($marketplace, ['listing_id' => $listing->id, 'attempt' => $attempt]);

        if (! $listing->variant || $listing->variant->trashed()) {
            $this->markFailed($listing, 'واریانت فروشگاه حذف شده است.');

            return 'skipped';
        }

        $driver = $marketplace->driver();

        try {
            $data = $this->builder->build($marketplace, $listing->variant);

            if (! $listing->isLinked()) {
                if (! $marketplace->supports(Capability::CREATE_LISTING)) {
                    $this->markFailed($listing, 'شناسه محصول/تنوع ' . $marketplace->title . ' وارد نشده است.');

                    return 'skipped';
                }

                $result = $driver->createListing($marketplace, $client, $listing, $data);

                $listing->update([
                    'external_id' => $result['external_id'],
                    'external_variant_id' => $result['external_variant_id'] ?? null,
                    'external_url' => $result['external_url'] ?? $listing->external_url,
                    'synced_price' => $data->price,
                    'synced_stock' => $data->stock,
                    'synced_active' => $data->active,
                    'content_hash' => $data->contentHash(),
                    'meta' => array_merge((array) $listing->meta, (array) ($result['meta'] ?? [])),
                ] + $this->successState());

                $marketplace->forceFill(['last_product_sync_at' => now(), 'last_stock_sync_at' => now()])->save();
                $client->log('listing.create', 'success', 'محصول «' . $data->title . '» در ' . $marketplace->title . ' ایجاد شد (شناسه ' . $result['external_id'] . ').');

                return 'created';
            }

            $fields = $this->changedFields($marketplace, $listing, $data, $force);

            if ($only !== null) {
                $fields = array_values(array_intersect($fields, $only));
            }

            if (! $fields) {
                if ($listing->sync_status !== 'synced') {
                    $listing->update($this->successState());
                }

                return 'unchanged';
            }

            $meta = $driver->updateListing($marketplace, $client, $listing, $data, $fields);

            $listing->update(array_filter([
                'synced_price' => in_array('price', $fields, true) ? $data->price : null,
                'synced_stock' => in_array('stock', $fields, true) || in_array('status', $fields, true) ? $data->stock : null,
                'synced_active' => in_array('status', $fields, true) || in_array('stock', $fields, true) ? $data->active : null,
                'content_hash' => in_array('content', $fields, true) ? $data->contentHash() : null,
                'meta' => $meta ? array_merge((array) $listing->meta, $meta) : null,
            ], fn ($v) => $v !== null) + $this->successState());

            $marketplace->forceFill(array_filter([
                'last_product_sync_at' => in_array('content', $fields, true) ? now() : null,
                'last_stock_sync_at' => array_intersect(['price', 'stock', 'status'], $fields) ? now() : null,
            ]))->save();

            return 'updated';
        } catch (MarketplaceException $e) {
            $this->markFailed($listing, $e->getMessage());

            if ($e->retryable) {
                throw $e;
            }

            return 'skipped';
        }
    }

    /** @return string[] */
    protected function changedFields(Marketplace $marketplace, MarketplaceListing $listing, $data, bool $force): array
    {
        $fields = [];

        if ($listing->sync_content && $marketplace->supports(Capability::UPDATE_CONTENT) && ($force || $listing->content_hash !== $data->contentHash())) {
            $fields[] = 'content';
        }

        if ($listing->sync_price && $marketplace->supports(Capability::UPDATE_PRICE) && ($force || $listing->synced_price !== $data->price)) {
            $fields[] = 'price';
        }

        if ($listing->sync_stock && $marketplace->supports(Capability::UPDATE_STOCK) && ($force || $listing->synced_stock !== $data->stock)) {
            $fields[] = 'stock';
        }

        if ($marketplace->supports(Capability::UPDATE_STATUS) && ($force || $listing->synced_active !== $data->active)) {
            $fields[] = 'status';
        }

        return $fields;
    }

    protected function successState(): array
    {
        return ['sync_status' => 'synced', 'failed_attempts' => 0, 'last_error' => null, 'last_synced_at' => now()];
    }

    public function markFailed(MarketplaceListing $listing, string $message): void
    {
        $listing->forceFill([
            'sync_status' => 'failed',
            'failed_attempts' => $listing->failed_attempts + 1,
            'last_error' => mb_substr($message, 0, 190),
        ])->save();

        $listing->marketplace?->forceFill(['last_error' => mb_substr($message, 0, 190), 'last_error_at' => now()])->save();
    }

    /**
     * ارسال همه اتصال‌های فعال یک مارکت‌پلیس به صف
     *
     * @param  string[]|null  $only
     */
    public function queueMarketplace(Marketplace $marketplace, ?array $only = null, bool $force = false): int
    {
        if (! $marketplace->isUsable()) {
            return 0;
        }

        $count = 0;

        $marketplace->listings()->active()->select('id')->chunkById(200, function ($listings) use ($only, $force, &$count) {
            foreach ($listings as $listing) {
                SyncListingJob::dispatch($listing->id, $only, $force);
                $count++;
            }
        });

        return $count;
    }

    public function remoteListings(Marketplace $marketplace, int $page = 1): RemoteListingPage
    {
        return $marketplace->driver()->remoteListings($marketplace, $this->manager->client($marketplace), $page);
    }

    // ---------------------------------------------------------------- سفارش‌ها

    /**
     * دریافت سفارش‌ها + به‌روزرسانی سفارش‌های باز قدیمی‌تر
     *
     * @return array{created: int, updated: int}
     */
    public function pullOrders(Marketplace $marketplace, int $attempt = 1): array
    {
        $stats = ['created' => 0, 'updated' => 0];

        if (! $marketplace->isUsable() || ! $marketplace->supports(Capability::PULL_ORDERS)) {
            return $stats;
        }

        $client = $this->manager->client($marketplace, ['attempt' => $attempt]);
        $driver = $marketplace->driver();
        $seen = [];
        $cursor = null;
        $pages = max(1, (int) config('marketplaces.order_pull_max_pages', 5));

        try {
            for ($i = 0; $i < $pages; $i++) {
                $page = $driver->fetchOrders($marketplace, $client, $cursor);

                foreach ($page->orders as $remote) {
                    $seen[] = $remote->externalId;
                    [$order, $created] = $this->importer->import($marketplace, $remote);
                    $stats[$created ? 'created' : 'updated']++;

                    if ($created) {
                        $client->withContext(['order_id' => $order->id])->log('order.import', 'success', 'سفارش ' . $remote->externalId . ' ثبت شد.');
                    }
                }

                if (! $page->nextCursor || $page->nextCursor === $cursor) {
                    break;
                }

                $cursor = $page->nextCursor;
            }

            // سفارش‌های باز که دیگر در فهرست نیستند (تحویل/لغو شده‌اند) تکی به‌روز می‌شوند
            MarketplaceOrder::where('marketplace_id', $marketplace->id)
                ->whereIn('status', ['new', 'processing', 'shipped', 'problem'])
                ->whereNotIn('external_id', $seen ?: ['-'])
                ->where(fn ($q) => $q->whereNull('synced_at')->orWhere('synced_at', '<', now()->subMinutes(30)))
                ->orderBy('synced_at')
                ->limit(20)
                ->get()
                ->each(function (MarketplaceOrder $order) use ($marketplace, $driver, $client, &$stats) {
                    $remote = $driver->fetchOrder($marketplace, $client, $order);

                    if ($remote) {
                        $this->importer->import($marketplace, $remote);
                        $stats['updated']++;
                    } else {
                        $order->update(['synced_at' => now()]);
                    }
                });
        } catch (MarketplaceException $e) {
            $marketplace->forceFill(['last_error' => mb_substr($e->getMessage(), 0, 190), 'last_error_at' => now()])->save();

            throw $e;
        }

        $marketplace->forceFill(['last_order_sync_at' => now()])->save();
        $client->log('orders.pull', 'success', "دریافت سفارش‌ها: {$stats['created']} جدید، {$stats['updated']} به‌روزرسانی.");

        return $stats;
    }

    /** دریافت دوباره یک سفارش از مارکت‌پلیس */
    public function refreshOrder(MarketplaceOrder $order): MarketplaceOrder
    {
        $marketplace = $order->marketplace;
        $remote = $marketplace->driver()->fetchOrder($marketplace, $this->manager->client($marketplace, ['order_id' => $order->id]), $order);

        if (! $remote) {
            throw new MarketplaceException('دریافت جزئیات این سفارش در API ' . $marketplace->title . ' امکان‌پذیر نیست.');
        }

        return $this->importer->import($marketplace, $remote)[0];
    }

    public function performOrderAction(MarketplaceOrder $order, string $action, array $input): MarketplaceOrder
    {
        $marketplace = $order->marketplace;
        $client = $this->manager->client($marketplace, ['order_id' => $order->id]);

        $marketplace->driver()->performOrderAction($marketplace, $client, $order, $action, $input);
        $client->log('order.status', 'success', 'عملیات «' . $action . '» روی سفارش ' . $order->external_id . ' انجام شد.', $input);

        try {
            return $this->refreshOrder($order);
        } catch (MarketplaceException) {
            return $order->fresh();
        }
    }

    // ---------------------------------------------------------------- ورودی‌ها

    public function handleWebhook(Marketplace $marketplace, Request $request): WebhookResult
    {
        $client = $this->manager->client($marketplace);
        $result = $marketplace->driver()->handleWebhook($marketplace, $client, $request);

        $client->log('webhook', 'success', $result->message ?? 'وب‌هوک دریافت شد.', [
            'ip' => $request->ip(),
            'body' => $request->json()->all() ?: $request->except(['token', 'secret']),
        ], 'in');

        foreach ($result->orders as $remote) {
            [$order, $created] = $this->importer->import($marketplace, $remote);

            if ($created) {
                $client->withContext(['order_id' => $order->id])->log('order.import', 'success', 'سفارش ' . $remote->externalId . ' از وب‌هوک ثبت شد.', [], 'in');
            }
        }

        if ($result->orders) {
            $marketplace->forceFill(['last_order_sync_at' => now()])->save();
        }

        if ($result->pullOrders && $marketplace->supports(Capability::PULL_ORDERS)) {
            PullOrdersJob::dispatch($marketplace->id);
        }

        return $result;
    }
}
