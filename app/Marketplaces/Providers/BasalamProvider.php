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
use App\Models\MarketplaceOrder;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * باسلام — Open API رسمی (https://openapi.basalam.com/v1 ، احراز هویت OAuth2 / توکن شخصی)
 * مطابق SDK رسمی basalam/php-sdk:
 *
 *  GET   /v1/users/me                                   کاربر و غرفه (vendor.id)
 *  GET   /v1/vendors/{vendor}/products                  محصولات غرفه
 *  POST  /v1/vendors/{vendor}/products                  ایجاد محصول
 *  PATCH /v1/products/{id}                              ویرایش محصول (نام، توضیح، تصویر، قیمت، موجودی، وضعیت)
 *  PATCH /v1/products/{id}/variations/{variation}       قیمت/موجودی تنوع
 *  POST  /v1/files                                      بارگذاری تصویر (file_type=product.photo)
 *  GET   /v1/vendor-parcels                             مرسوله‌های غرفه (سفارش‌ها)
 *  GET   /v1/vendor-parcels/{id}
 *  POST  /v1/vendor-parcels/{id}/set-preparation        در حال آماده‌سازی
 *  POST  /v1/vendor-parcels/{id}/set-posted             ارسال شد (روش ارسال + کد رهگیری)
 *
 * مبالغ API باسلام به ریال است. هر واریانت فروشگاه به یک محصول (یا تنوع) باسلام وصل می‌شود.
 * بدنه وب‌هوک معتبر فرض نمی‌شود؛ فقط باعث دریافت سفارش‌ها از API می‌شود.
 */
class BasalamProvider extends AbstractMarketplaceProvider
{
    // وضعیت‌های محصول
    protected const PRODUCT_PUBLISHED = 2976;
    protected const PRODUCT_UNPUBLISHED = 3790;

    // وضعیت مرسوله => وضعیت نرمال‌شده
    protected const PARCEL_STATUSES = [
        3739 => 'new',           // سفارش جدید
        3237 => 'processing',    // در حال آماده‌سازی
        3238 => 'shipped',       // ارسال شده
        5017 => 'shipped',       // کد رهگیری نادرست
        3572 => 'problem',       // کالا نرسیده
        3740 => 'problem',       // گزارش مشکل
        4633 => 'problem',       // درخواست لغو از سوی مشتری
        5075 => 'problem',       // درخواست تمدید مهلت ارسال
        3195 => 'delivered',     // رضایت مشتری
        3233 => 'returned',      // نارضایتی قطعی
        3067 => 'cancelled',     // لغو
    ];

    // وضعیت‌هایی که هنوز ممکن است تغییر کنند (برای دریافت دوره‌ای)
    protected const OPEN_PARCEL_STATUSES = [3739, 3237, 3238, 5017, 3572, 3740, 4633, 5075];

    protected const SHIPPING_METHODS = [
        3197 => 'پست سفارشی',
        3198 => 'پست پیشتاز',
        3259 => 'پیک',
        4040 => 'تیپاکس',
        6101 => 'چاپار',
        6102 => 'ماهکس',
        6110 => 'آمادست',
        6111 => 'دکا',
        6112 => 'چیتا',
        5137 => 'باربری',
    ];

    public function key(): string
    {
        return 'basalam';
    }

    public function label(): string
    {
        return 'باسلام';
    }

    public function description(): string
    {
        return 'ایجاد و به‌روزرسانی محصولات غرفه، همگام‌سازی قیمت و موجودی، دریافت و مدیریت مرسوله‌ها از طریق Open API رسمی باسلام.';
    }

    public function capabilities(): array
    {
        return [
            Capability::CREATE_LISTING,
            Capability::UPDATE_CONTENT,
            Capability::UPDATE_PRICE,
            Capability::UPDATE_STOCK,
            Capability::UPDATE_STATUS,
            Capability::REMOTE_LISTINGS,
            Capability::PULL_ORDERS,
            Capability::WEBHOOK,
            Capability::ORDER_ACTIONS,
        ];
    }

    public function limitations(): array
    {
        return [
            'ایجاد محصول به «شناسه دسته‌بندی باسلام» و حداقل یک تصویر نیاز دارد؛ محصولات پس از ایجاد ممکن است در صف بررسی باسلام قرار بگیرند.',
            'هر واریانت فروشگاه به‌صورت یک محصول جدا در باسلام ایجاد می‌شود؛ برای اتصال به تنوع یک محصول موجود، شناسه تنوع را دستی وارد کنید.',
            'بدنه وب‌هوک باسلام امضا ندارد؛ وب‌هوک فقط دریافت سفارش‌ها از API را آغاز می‌کند.',
            'توکن دسترسی باید دامنه‌های vendor.product.read/write و vendor.parcel.read/write را داشته باشد.',
        ];
    }

    public function credentialFields(): array
    {
        return [
            'access_token' => [
                'label' => 'توکن دسترسی (Access Token)',
                'type' => 'secret',
                'required' => true,
                'help' => 'توکن شخصی یا OAuth از developers.basalam.com',
            ],
            'refresh_token' => [
                'label' => 'Refresh Token',
                'type' => 'secret',
                'help' => 'اختیاری؛ برای تمدید خودکار توکن منقضی',
            ],
            'client_id' => [
                'label' => 'Client ID',
                'type' => 'secret',
                'help' => 'اختیاری؛ برای تمدید توکن',
            ],
            'client_secret' => [
                'label' => 'Client Secret',
                'type' => 'secret',
                'help' => 'اختیاری؛ برای تمدید توکن',
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
                'default' => 'https://openapi.basalam.com',
                'group' => 'اتصال',
            ],
            'token_url' => [
                'label' => 'آدرس تمدید توکن',
                'type' => 'url',
                'default' => 'https://auth.basalam.com/oauth/token',
                'group' => 'اتصال',
            ],
            'vendor_id' => [
                'label' => 'شناسه غرفه (Vendor ID)',
                'type' => 'number',
                'help' => 'با «تست اتصال» به‌صورت خودکار تکمیل می‌شود.',
                'group' => 'اتصال',
            ],
            'price_unit' => $this->unitOptions('rial'),
            'category_id' => [
                'label' => 'شناسه دسته‌بندی باسلام برای محصولات جدید',
                'type' => 'number',
                'help' => 'برای «ایجاد محصول در باسلام» الزامی است.',
                'group' => 'محصول',
            ],
            'preparation_days' => [
                'label' => 'زمان آماده‌سازی (روز)',
                'type' => 'number',
                'default' => 2,
                'group' => 'محصول',
            ],
            'weight' => [
                'label' => 'وزن پیش‌فرض کالا (گرم)',
                'type' => 'number',
                'default' => 500,
                'help' => 'در صورت نداشتن وزن واریانت',
                'group' => 'محصول',
            ],
            'package_weight' => [
                'label' => 'وزن با بسته‌بندی (گرم)',
                'type' => 'number',
                'default' => 600,
                'group' => 'محصول',
            ],
        ];
    }

    public function isConfigured(Marketplace $marketplace): bool
    {
        return filled($marketplace->credential('access_token')) && filled($marketplace->setting('base_url'));
    }

    // ---------------------------------------------------------------- اتصال

    public function testConnection(Marketplace $marketplace, MarketplaceClient $client): ConnectionResult
    {
        try {
            $me = $this->api($marketplace, $client, 'connection', 'GET', '/v1/users/me')->json();
        } catch (Throwable $e) {
            return $this->connectionFailure($e);
        }

        $vendorId = data_get($me, 'vendor.id');

        if (! $vendorId) {
            return ConnectionResult::failure('اتصال برقرار شد اما این حساب غرفه فعال در باسلام ندارد.');
        }

        return ConnectionResult::success(
            'متصل به غرفه «' . (data_get($me, 'vendor.title') ?: $vendorId) . '»',
            ['vendor_id' => (int) $vendorId]
        );
    }

    /**
     * درخواست با توکن؛ در صورت 401 و وجود Refresh Token، توکن تمدید و یک‌بار تکرار می‌شود.
     */
    protected function api(Marketplace $marketplace, MarketplaceClient $client, string $operation, string $method, string $path, array $options = []): Response
    {
        $url = $this->baseUrl($marketplace) . $path;
        $response = $client->send($operation, $method, $url, $options + ['token' => (string) $marketplace->credential('access_token')]);

        if ($response->status() === 401 && $this->refreshToken($marketplace, $client)) {
            $response = $client->send($operation, $method, $url, array_merge($options, ['token' => (string) $marketplace->credential('access_token')]));
        }

        if ($response->failed()) {
            throw $client->exceptionFor($response);
        }

        return $response;
    }

    protected function refreshToken(Marketplace $marketplace, MarketplaceClient $client): bool
    {
        $refresh = $marketplace->credential('refresh_token');

        if (! $refresh || ! $marketplace->credential('client_id') || ! $marketplace->credential('client_secret')) {
            return false;
        }

        try {
            $response = $client->send('auth', 'POST', (string) $marketplace->setting('token_url'), [
                'json' => [
                    'grant_type' => 'refresh_token',
                    'refresh_token' => $refresh,
                    'client_id' => $marketplace->credential('client_id'),
                    'client_secret' => $marketplace->credential('client_secret'),
                ],
            ]);
        } catch (MarketplaceException) {
            return false;
        }

        if (! $response->successful() || ! $response->json('access_token')) {
            return false;
        }

        $marketplace->forceFill(['credentials' => array_merge((array) $marketplace->credentials, array_filter([
            'access_token' => $response->json('access_token'),
            'refresh_token' => $response->json('refresh_token'),
        ]))])->save();

        return true;
    }

    protected function vendorId(Marketplace $marketplace, MarketplaceClient $client): int
    {
        if ($id = (int) $marketplace->setting('vendor_id')) {
            return $id;
        }

        $result = $this->testConnection($marketplace, $client);

        if (! $result->ok) {
            throw new MarketplaceException($result->message);
        }

        $marketplace->mergeSettings($result->settings);

        return (int) $result->settings['vendor_id'];
    }

    // ---------------------------------------------------------------- محصولات

    public function remoteListings(Marketplace $marketplace, MarketplaceClient $client, int $page = 1): RemoteListingPage
    {
        $vendorId = $this->vendorId($marketplace, $client);
        $body = (array) $this->api($marketplace, $client, 'remote.listings', 'GET', "/v1/vendors/{$vendorId}/products", [
            'query' => ['page' => $page, 'per_page' => 50],
        ])->json();

        $items = [];
        foreach ((array) ($body['data'] ?? []) as $product) {
            $items[] = new RemoteListing(
                externalId: (string) $product['id'],
                externalVariantId: null,
                title: (string) ($product['title'] ?? $product['name'] ?? $product['id']),
                sku: $product['sku'] ?? null,
                price: isset($product['primary_price']) ? $this->toLocalAmount($marketplace, $product['primary_price']) : null,
                stock: isset($product['inventory']) ? (int) $product['inventory'] : null,
                active: isset($product['status']) ? (int) data_get($product, 'status.id', $product['status']) === self::PRODUCT_PUBLISHED : null,
                url: $product['url'] ?? null,
            );
        }

        $totalPages = (int) ($body['total_page'] ?? 0);

        return new RemoteListingPage($items, $totalPages ? $page < $totalPages : count($items) >= 50);
    }

    public function createListing(Marketplace $marketplace, MarketplaceClient $client, MarketplaceListing $listing, ListingData $data): array
    {
        $vendorId = $this->vendorId($marketplace, $client);
        $categoryId = (int) $marketplace->setting('category_id');

        if (! $categoryId) {
            throw new MarketplaceException('شناسه دسته‌بندی باسلام در تنظیمات وارد نشده است.');
        }

        $photos = $this->uploadPhotos($marketplace, $client, $data);

        if (! $photos) {
            throw new MarketplaceException('برای ایجاد محصول در باسلام حداقل یک تصویر لازم است.');
        }

        $payload = array_filter([
            'name' => $this->name($data),
            'description' => $data->description,
            'brief' => $data->brief ? mb_substr($data->brief, 0, 250) : null,
            'category_id' => $categoryId,
            'status' => $data->active ? self::PRODUCT_PUBLISHED : self::PRODUCT_UNPUBLISHED,
            'primary_price' => $this->toRemoteAmount($marketplace, $data->price),
            'stock' => $data->stock,
            'sku' => $data->sku,
            'preparation_days' => (int) $marketplace->setting('preparation_days', 2),
            'weight' => $data->weight ?: (int) $marketplace->setting('weight', 500),
            'package_weight' => max($data->weight ?: 0, (int) $marketplace->setting('package_weight', 600)),
            'photo' => $photos[0],
            'photos' => array_slice($photos, 1) ?: null,
            'is_wholesale' => false,
        ], fn ($v) => $v !== null);

        $product = (array) $this->api($marketplace, $client, 'listing.create', 'POST', "/v1/vendors/{$vendorId}/products", ['json' => $payload])->json();

        if (empty($product['id'])) {
            throw new MarketplaceException('پاسخ باسلام شناسه محصول ندارد.');
        }

        return [
            'external_id' => (string) $product['id'],
            'external_url' => $product['url'] ?? null,
            'meta' => ['photo_ids' => $photos, 'images_hash' => $data->imagesHash()],
        ];
    }

    public function updateListing(Marketplace $marketplace, MarketplaceClient $client, MarketplaceListing $listing, ListingData $data, array $fields): array
    {
        $meta = [];
        $productPayload = [];
        $variationPayload = [];

        if (in_array('content', $fields, true)) {
            $productPayload['name'] = $this->name($data);
            $productPayload['description'] = $data->description;
            if ($data->brief) {
                $productPayload['brief'] = mb_substr($data->brief, 0, 250);
            }

            // تصاویر فقط در صورت تغییر دوباره بارگذاری می‌شوند
            if (($listing->meta['images_hash'] ?? null) !== $data->imagesHash() && $data->images) {
                $photos = $this->uploadPhotos($marketplace, $client, $data);
                if ($photos) {
                    $productPayload['photo'] = $photos[0];
                    $productPayload['photos'] = array_slice($photos, 1);
                    $meta = ['photo_ids' => $photos, 'images_hash' => $data->imagesHash()];
                }
            }
        }

        // تنوع یک محصول: قیمت/موجودی روی تنوع، بقیه روی محصول
        $priceStock = [];

        if (in_array('price', $fields, true)) {
            $priceStock['primary_price'] = $this->toRemoteAmount($marketplace, $data->price);
        }

        if (in_array('stock', $fields, true)) {
            $priceStock['stock'] = $data->stock;
        }

        if ($listing->external_variant_id) {
            $variationPayload = $priceStock;
        } else {
            $productPayload += $priceStock;
        }

        if (in_array('status', $fields, true)) {
            $productPayload['status'] = $data->active ? self::PRODUCT_PUBLISHED : self::PRODUCT_UNPUBLISHED;
        }

        if ($productPayload) {
            $this->api($marketplace, $client, 'listing.update', 'PATCH', '/v1/products/' . $listing->external_id, ['json' => $productPayload]);
        }

        if ($variationPayload) {
            $this->api($marketplace, $client, 'stock', 'PATCH', '/v1/products/' . $listing->external_id . '/variations/' . $listing->external_variant_id, ['json' => $variationPayload]);
        }

        return $meta;
    }

    protected function name(ListingData $data): string
    {
        return mb_substr($data->title . ($data->variantTitle ? ' - ' . $data->variantTitle : ''), 0, 120);
    }

    /** @return int[] شناسه فایل‌های بارگذاری‌شده */
    protected function uploadPhotos(Marketplace $marketplace, MarketplaceClient $client, ListingData $data): array
    {
        $ids = [];

        foreach (array_slice($data->images, 0, 10) as $image) {
            $contents = null;

            if ($image['path'] && Storage::disk($image['disk'])->exists($image['path'])) {
                $contents = Storage::disk($image['disk'])->get($image['path']);
            }

            if ($contents === null) {
                continue; // تصویر خارجی/ناموجود بارگذاری نمی‌شود
            }

            $response = $this->api($marketplace, $client, 'listing.upload', 'POST', '/v1/files', [
                'multipart' => [
                    ['name' => 'file_type', 'contents' => 'product.photo'],
                    ['name' => 'file', 'contents' => $contents, 'filename' => basename($image['path'])],
                ],
            ]);

            if ($id = $response->json('id')) {
                $ids[] = (int) $id;
            }
        }

        return $ids;
    }

    // ---------------------------------------------------------------- سفارش‌ها

    public function fetchOrders(Marketplace $marketplace, MarketplaceClient $client, ?string $cursor = null): OrderPage
    {
        $query = array_filter([
            'per_page' => 30,
            'statuses' => implode(',', self::OPEN_PARCEL_STATUSES),
            'cursor' => $cursor,
        ]);

        $body = (array) $this->api($marketplace, $client, 'orders.pull', 'GET', '/v1/vendor-parcels', ['query' => $query])->json();

        $orders = array_map(fn ($parcel) => $this->mapParcel($marketplace, (array) $parcel), (array) ($body['data'] ?? []));

        return new OrderPage($orders, isset($body['next_cursor']) && $body['next_cursor'] !== '' ? (string) $body['next_cursor'] : null);
    }

    public function fetchOrder(Marketplace $marketplace, MarketplaceClient $client, MarketplaceOrder $order): ?RemoteOrder
    {
        $parcel = (array) $this->api($marketplace, $client->withContext(['order_id' => $order->id]), 'orders.pull', 'GET', '/v1/vendor-parcels/' . $order->external_id)->json();

        return empty($parcel['id']) ? null : $this->mapParcel($marketplace, $parcel);
    }

    protected function mapParcel(Marketplace $marketplace, array $parcel): RemoteOrder
    {
        $statusId = (int) data_get($parcel, 'status.id', $parcel['status'] ?? 0);
        $rawItems = (array) ($parcel['items'] ?? []);
        $itemsTotal = $this->toLocalAmount($marketplace, $parcel['total_items_price'] ?? 0);

        // price هر قلم: اگر جمع price ها برابر جمع کل باشد، قیمت سطر است نه واحد
        $sumPrices = array_sum(array_map(fn ($i) => $this->toLocalAmount($marketplace, $i['price'] ?? 0), $rawItems));
        $sumLines = array_sum(array_map(fn ($i) => $this->toLocalAmount($marketplace, $i['price'] ?? 0) * max(1, (int) ($i['quantity'] ?? 1)), $rawItems));
        $priceIsLineTotal = $itemsTotal > 0 && $sumPrices === $itemsTotal && $sumLines !== $itemsTotal;

        $items = array_map(function ($item) use ($marketplace, $priceIsLineTotal) {
            $quantity = max(1, (int) ($item['quantity'] ?? 1));
            $price = $this->toLocalAmount($marketplace, $item['price'] ?? 0);

            return new RemoteOrderItem(
                externalItemId: isset($item['id']) ? (string) $item['id'] : null,
                externalProductId: data_get($item, 'product.id') !== null ? (string) data_get($item, 'product.id') : null,
                externalVariantId: data_get($item, 'variation.id') !== null ? (string) data_get($item, 'variation.id') : null,
                title: (string) ($item['title'] ?? data_get($item, 'product.name', '')),
                quantity: $quantity,
                price: $priceIsLineTotal ? intdiv($price, $quantity) : $price,
            );
        }, $rawItems);

        $shipping = $this->toLocalAmount($marketplace, $parcel['shipping_cost'] ?? 0);
        $recipient = (array) data_get($parcel, 'order.customer.recipient', []);

        return new RemoteOrder(
            externalId: (string) $parcel['id'],
            status: self::PARCEL_STATUSES[$statusId] ?? 'new',
            externalStatus: (string) (data_get($parcel, 'status.title') ?: $statusId),
            items: $items,
            externalOrderId: data_get($parcel, 'order.id') !== null ? (string) data_get($parcel, 'order.id') : null,
            paymentStatus: data_get($parcel, 'order.paid_at') ? 'paid' : 'unpaid',
            customerName: $recipient['name'] ?? data_get($parcel, 'order.customer.user.name'),
            customerMobile: $recipient['mobile'] ?? null,
            province: data_get($parcel, 'order.customer.city.parent.title'),
            city: data_get($parcel, 'order.customer.city.title'),
            address: trim(implode(' ', array_filter([
                $recipient['postal_address'] ?? null,
                isset($recipient['house_number']) ? 'پلاک ' . $recipient['house_number'] : null,
                isset($recipient['house_unit']) ? 'واحد ' . $recipient['house_unit'] : null,
            ]))) ?: null,
            postalCode: $recipient['postal_code'] ?? null,
            itemsAmount: $itemsTotal,
            shippingAmount: $shipping,
            totalAmount: $itemsTotal + $shipping,
            trackingCode: data_get($parcel, 'post_receipt.tracking_code'),
            orderedAt: $this->date(data_get($parcel, 'order.created_at', $parcel['created_at'] ?? null)),
            paidAt: $this->date(data_get($parcel, 'order.paid_at')),
            payload: $parcel,
        );
    }

    public function orderActions(Marketplace $marketplace, MarketplaceOrder $order): array
    {
        $actions = [];

        if ($order->status === 'new') {
            $actions['preparation'] = ['label' => 'ثبت «در حال آماده‌سازی» در باسلام'];
        }

        if (in_array($order->status, ['new', 'processing'], true)) {
            $actions['posted'] = [
                'label' => 'ثبت ارسال در باسلام',
                'fields' => [
                    'shipping_method' => ['label' => 'روش ارسال', 'type' => 'select', 'options' => self::SHIPPING_METHODS, 'required' => true],
                    'tracking_code' => ['label' => 'کد رهگیری', 'type' => 'text', 'required' => true],
                ],
            ];
        }

        return $actions;
    }

    public function performOrderAction(Marketplace $marketplace, MarketplaceClient $client, MarketplaceOrder $order, string $action, array $input): void
    {
        $client = $client->withContext(['order_id' => $order->id]);
        $path = '/v1/vendor-parcels/' . $order->external_id;

        match ($action) {
            'preparation' => $this->api($marketplace, $client, 'order.status', 'POST', $path . '/set-preparation'),
            'posted' => $this->api($marketplace, $client, 'order.status', 'POST', $path . '/set-posted', ['json' => [
                'shipping_method' => (int) ($input['shipping_method'] ?? 0),
                'tracking_code' => (string) ($input['tracking_code'] ?? ''),
            ]]),
            default => throw new MarketplaceException('عملیات نامعتبر است.'),
        };
    }

    public function handleWebhook(Marketplace $marketplace, MarketplaceClient $client, Request $request): WebhookResult
    {
        return WebhookResult::pull('وب‌هوک باسلام دریافت شد؛ سفارش‌ها از API دریافت می‌شوند.');
    }
}
