<?php

namespace App\Marketplaces\Providers;

use App\Marketplaces\Capability;
use App\Marketplaces\Data\ConnectionResult;
use App\Marketplaces\Data\ListingData;
use App\Marketplaces\Data\OrderPage;
use App\Marketplaces\Data\RemoteListing;
use App\Marketplaces\Data\RemoteListingPage;
use App\Marketplaces\Data\RemoteOrder;
use App\Marketplaces\Data\RemoteOrderItem;
use App\Marketplaces\Data\WebhookResult;
use App\Marketplaces\Exceptions\MarketplaceException;
use App\Marketplaces\MarketplaceClient;
use App\Models\Marketplace;
use App\Models\MarketplaceListing;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Throwable;

/**
 * دیجی‌کالا — API پنل فروشندگان (توکن از «پنل فروشندگان > API»)
 *
 * آنچه API فروشنده پشتیبانی می‌کند و اینجا پیاده شده است:
 *  - دریافت فهرست تنوع‌های فروشنده (DKPC) برای اتصال/تطبیق با کد فروشنده (SKU)
 *  - به‌روزرسانی قیمت و موجودی فروشنده روی تنوع
 *  - دریافت سفارش‌های جدید (وب‌هوک + در صورت تنظیم مسیر، دریافت دوره‌ای)
 *
 * آنچه پشتیبانی نمی‌شود و پیاده نشده است:
 *  - ایجاد کالا یا ویرایش نام/توضیحات/تصاویر (کاتالوگ متعلق به دیجی‌کالاست و از پنل درج می‌شود)
 *  - تغییر وضعیت سفارش (ارسال و پردازش در فرآیند دیجی‌کالا/پنل انجام می‌شود)
 *
 * مستندات این API عمومی نیست و در پنل فروشندگان ارائه می‌شود؛ بنابراین مسیرها و نام فیلدها
 * از تنظیمات خوانده می‌شوند تا بدون تغییر کد با مستندات نسخه حساب شما تطبیق داده شوند.
 */
class DigikalaProvider extends AbstractMarketplaceProvider
{
    public function key(): string
    {
        return 'digikala';
    }

    public function label(): string
    {
        return 'دیجی‌کالا';
    }

    public function description(): string
    {
        return 'اتصال تنوع‌های فروشنده (DKPC) به کالاهای فروشگاه، همگام‌سازی قیمت و موجودی و دریافت سفارش‌های جدید.';
    }

    public function capabilities(): array
    {
        return [
            Capability::UPDATE_PRICE,
            Capability::UPDATE_STOCK,
            Capability::REMOTE_LISTINGS,
            Capability::PULL_ORDERS,
            Capability::WEBHOOK,
        ];
    }

    public function limitations(): array
    {
        return [
            'ایجاد کالا و ویرایش نام، توضیحات و تصاویر از طریق API فروشنده امکان‌پذیر نیست؛ کالا باید در پنل فروشندگان درج و تأیید شود و سپس اینجا متصل گردد.',
            'غیرفعال شدن محصول در فروشگاه با صفر کردن موجودی در دیجی‌کالا اعمال می‌شود.',
            'وب‌هوک دیجی‌کالا لغو سفارش را ارسال نمی‌کند؛ لغو را از صفحه سفارش‌ها ثبت کنید تا موجودی بازگردد.',
            'مستندات API در پنل فروشندگان ارائه می‌شود؛ مسیرها و نام فیلدها را مطابق آن در تنظیمات بررسی کنید.',
            'تطبیق خودکار وقتی ممکن است که «کد فروشنده» در دیجی‌کالا با SKU واریانت در فروشگاه یکسان باشد.',
        ];
    }

    public function credentialFields(): array
    {
        return [
            'api_token' => [
                'label' => 'توکن API فروشنده',
                'type' => 'secret',
                'required' => true,
                'help' => 'از پنل فروشندگان دیجی‌کالا > بخش API',
            ],
        ];
    }

    protected function providerSettingFields(): array
    {
        return [
            'base_url' => [
                'label' => 'آدرس API',
                'type' => 'url',
                'required' => true,
                'default' => 'https://seller.digikala.com',
                'group' => 'اتصال',
            ],
            'auth_header' => [
                'label' => 'نام هدر احراز هویت',
                'type' => 'text',
                'default' => 'Authorization',
                'group' => 'اتصال',
            ],
            'auth_prefix' => [
                'label' => 'پیشوند توکن',
                'type' => 'select',
                'default' => 'Bearer',
                'options' => ['Bearer' => 'Bearer <token>', 'none' => 'فقط توکن'],
                'group' => 'اتصال',
            ],
            'price_unit' => $this->unitOptions('rial'),
            'variants_path' => [
                'label' => 'مسیر فهرست تنوع‌ها',
                'type' => 'text',
                'default' => '/api/v2/variants',
                'help' => 'پارامترهای page و size ارسال می‌شوند.',
                'group' => 'مسیرهای API',
            ],
            'variant_update_path' => [
                'label' => 'مسیر به‌روزرسانی تنوع',
                'type' => 'text',
                'default' => '/api/v2/variants/{id}',
                'help' => '{id} با شناسه تنوع (DKPC) جایگزین می‌شود.',
                'group' => 'مسیرهای API',
            ],
            'variant_update_method' => [
                'label' => 'متد به‌روزرسانی تنوع',
                'type' => 'select',
                'default' => 'PUT',
                'options' => ['PUT' => 'PUT', 'PATCH' => 'PATCH', 'POST' => 'POST'],
                'group' => 'مسیرهای API',
            ],
            'price_field' => [
                'label' => 'نام فیلد قیمت',
                'type' => 'text',
                'default' => 'price',
                'group' => 'مسیرهای API',
            ],
            'stock_field' => [
                'label' => 'نام فیلد موجودی',
                'type' => 'text',
                'default' => 'seller_stock',
                'group' => 'مسیرهای API',
            ],
            'orders_path' => [
                'label' => 'مسیر فهرست سفارش‌ها',
                'type' => 'text',
                'default' => '',
                'help' => 'خالی = فقط دریافت از طریق وب‌هوک',
                'group' => 'مسیرهای API',
            ],
        ];
    }

    // ---------------------------------------------------------------- اتصال

    protected function headers(Marketplace $marketplace): array
    {
        $token = (string) $marketplace->credential('api_token');
        $prefix = $marketplace->setting('auth_prefix', 'Bearer');

        return [(string) $marketplace->setting('auth_header', 'Authorization') => $prefix === 'none' ? $token : $prefix . ' ' . $token];
    }

    protected function api(Marketplace $marketplace, MarketplaceClient $client, string $operation, string $method, string $path, array $options = []): Response
    {
        $options['headers'] = array_merge($this->headers($marketplace), (array) ($options['headers'] ?? []));

        return $client->sendOrFail($operation, $method, $this->baseUrl($marketplace) . '/' . ltrim($path, '/'), $options);
    }

    public function testConnection(Marketplace $marketplace, MarketplaceClient $client): ConnectionResult
    {
        try {
            $page = $this->remoteListings($marketplace, $client->withContext([]), 1, 1);
        } catch (Throwable $e) {
            return $this->connectionFailure($e);
        }

        return ConnectionResult::success('اتصال به API فروشندگان برقرار است' . ($page->items ? '.' : ' (تنوعی یافت نشد).'));
    }

    // ---------------------------------------------------------------- تنوع‌ها

    public function remoteListings(Marketplace $marketplace, MarketplaceClient $client, int $page = 1, int $size = 50): RemoteListingPage
    {
        $body = (array) $this->api($marketplace, $client, $size === 1 ? 'connection' : 'remote.listings', 'GET', (string) $marketplace->setting('variants_path'), [
            'query' => ['page' => $page, 'size' => $size],
        ])->json();

        $rows = $this->pick($body, ['data.items', 'data.variants', 'items', 'variants', 'data'], []);
        $rows = is_array($rows) && array_is_list($rows) ? $rows : [];

        $items = [];
        foreach ($rows as $row) {
            $row = (array) $row;
            $id = $this->pick($row, ['id', 'variant_id', 'dkpc']);

            if ($id === null) {
                continue;
            }

            $price = $this->pick($row, ['price_sale', 'selling_price', 'price.selling_price', 'price']);

            $items[] = new RemoteListing(
                externalId: (string) $id,
                externalVariantId: null,
                title: (string) $this->pick($row, ['title', 'product_title', 'product.title', 'product_variant_title'], $id),
                sku: ($sku = $this->pick($row, ['supplier_code', 'seller_sku', 'sku', 'seller_code'])) !== null ? (string) $sku : null,
                price: is_numeric($price) ? $this->toLocalAmount($marketplace, $price) : null,
                stock: is_numeric($stock = $this->pick($row, ['seller_stock', 'stock.seller_stock', 'marketplace_seller_stock', 'stock'])) ? (int) $stock : null,
                active: ($active = $this->pick($row, ['is_active', 'active'])) !== null ? (bool) $active : null,
                url: $this->pick($row, ['url', 'product.url']),
            );
        }

        $totalPages = (int) $this->pick($body, ['data.pager.total_pages', 'pager.total_pages', 'meta.last_page', 'total_pages'], 0);

        return new RemoteListingPage($items, $totalPages ? $page < $totalPages : count($items) >= $size);
    }

    public function updateListing(Marketplace $marketplace, MarketplaceClient $client, MarketplaceListing $listing, ListingData $data, array $fields): array
    {
        $payload = [];

        if (in_array('price', $fields, true)) {
            $payload[(string) $marketplace->setting('price_field', 'price')] = $this->toRemoteAmount($marketplace, $data->price);
        }

        // وضعیت فعال/غیرفعال در API نیست => محصول غیرفعال با موجودی صفر
        if (in_array('stock', $fields, true) || in_array('status', $fields, true)) {
            $payload[(string) $marketplace->setting('stock_field', 'seller_stock')] = $data->active ? $data->stock : 0;
        }

        if ($payload) {
            $path = str_replace('{id}', rawurlencode((string) $listing->external_id), (string) $marketplace->setting('variant_update_path'));
            $this->api($marketplace, $client, 'stock', (string) $marketplace->setting('variant_update_method', 'PUT'), $path, ['json' => $payload]);
        }

        return [];
    }

    // ---------------------------------------------------------------- سفارش‌ها

    public function fetchOrders(Marketplace $marketplace, MarketplaceClient $client, ?string $cursor = null): OrderPage
    {
        $path = (string) $marketplace->setting('orders_path');

        if ($path === '') {
            return new OrderPage([]); // فقط وب‌هوک
        }

        $page = max(1, (int) $cursor);
        $body = (array) $this->api($marketplace, $client, 'orders.pull', 'GET', $path, ['query' => ['page' => $page, 'size' => 50]])->json();

        $rows = $this->pick($body, ['data.items', 'data.orders', 'items', 'orders', 'data'], []);
        $rows = is_array($rows) && array_is_list($rows) ? $rows : [];

        $orders = array_values(array_filter(array_map(fn ($row) => $this->mapOrder($marketplace, (array) $row), $rows)));
        $totalPages = (int) $this->pick($body, ['data.pager.total_pages', 'pager.total_pages', 'meta.last_page', 'total_pages'], 0);

        return new OrderPage($orders, ($totalPages ? $page < $totalPages : count($rows) >= 50) ? (string) ($page + 1) : null);
    }

    public function handleWebhook(Marketplace $marketplace, MarketplaceClient $client, Request $request): WebhookResult
    {
        $payload = (array) $request->json()->all() ?: $request->all();
        $rows = $this->pick($payload, ['data.orders', 'orders'], null);
        $rows = is_array($rows) && array_is_list($rows) ? $rows : [$this->pick($payload, ['data.order', 'order', 'data'], $payload)];

        $orders = array_values(array_filter(array_map(fn ($row) => $this->mapOrder($marketplace, (array) $row), $rows)));

        if (! $orders) {
            return filled($marketplace->setting('orders_path'))
                ? WebhookResult::pull('ساختار وب‌هوک شناخته نشد؛ سفارش‌ها از API دریافت می‌شوند.')
                : WebhookResult::ignored('سفارشی در بدنه وب‌هوک یافت نشد.');
        }

        return new WebhookResult($orders, false, count($orders) . ' سفارش از وب‌هوک دریافت شد.');
    }

    /**
     * تبدیل سفارش دیجی‌کالا (ساختار با نام‌های رایج فیلدها خوانده می‌شود؛ کل داده در payload ذخیره می‌شود)
     */
    protected function mapOrder(Marketplace $marketplace, array $row): ?RemoteOrder
    {
        $id = $this->pick($row, ['order_item_id', 'shipment_id', 'id', 'order_id', 'order_code']);

        if ($id === null) {
            return null;
        }

        $rawItems = $this->pick($row, ['items', 'order_items', 'products'], null);
        $rawItems = is_array($rawItems) && array_is_list($rawItems) ? $rawItems : [$row];

        $items = [];
        foreach ($rawItems as $item) {
            $item = (array) $item;
            $variantId = $this->pick($item, ['variant_id', 'dkpc', 'variant.id', 'product_variant_id']);

            if ($variantId === null) {
                continue;
            }

            $quantity = max(1, (int) $this->pick($item, ['quantity', 'count', 'qty'], 1));
            $price = $this->pick($item, ['selling_price', 'price', 'unit_price', 'price.selling_price'], 0);

            $items[] = new RemoteOrderItem(
                externalItemId: ($itemId = $this->pick($item, ['order_item_id', 'id'])) !== null ? (string) $itemId : null,
                externalProductId: (string) $variantId,
                externalVariantId: null,
                title: (string) $this->pick($item, ['title', 'product_title', 'variant_title', 'product.title'], 'کالا ' . $variantId),
                quantity: $quantity,
                price: is_numeric($price) ? $this->toLocalAmount($marketplace, $price) : 0,
                sku: ($sku = $this->pick($item, ['supplier_code', 'seller_sku', 'sku'])) !== null ? (string) $sku : null,
            );
        }

        $status = strtolower((string) $this->pick($row, ['status', 'order_status', 'shipment_status'], 'new'));

        return new RemoteOrder(
            externalId: (string) $id,
            status: match (true) {
                str_contains($status, 'cancel') => 'cancelled',
                str_contains($status, 'return') => 'returned',
                str_contains($status, 'deliver') => 'delivered',
                str_contains($status, 'ship') || str_contains($status, 'sent') => 'shipped',
                str_contains($status, 'process') || str_contains($status, 'prepar') => 'processing',
                default => 'new',
            },
            externalStatus: $status !== '' ? $status : null,
            items: $items,
            externalOrderId: ($orderId = $this->pick($row, ['order_id', 'order_code'])) !== null ? (string) $orderId : null,
            customerName: null,   // اطلاعات خریدار در API فروشنده ارائه نمی‌شود (ارسال توسط دیجی‌کالا)
            itemsAmount: array_sum(array_map(fn ($i) => $i->price * $i->quantity, $items)),
            orderedAt: $this->date($this->pick($row, ['created_at', 'order_date', 'date'])),
            payload: $row,
        );
    }

    public function createListing(Marketplace $marketplace, MarketplaceClient $client, MarketplaceListing $listing, ListingData $data): array
    {
        throw new MarketplaceException('ایجاد کالا در دیجی‌کالا فقط از پنل فروشندگان امکان‌پذیر است؛ پس از تأیید کالا، شناسه تنوع (DKPC) را اینجا متصل کنید.');
    }
}
