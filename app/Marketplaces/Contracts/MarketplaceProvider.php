<?php

namespace App\Marketplaces\Contracts;

use App\Marketplaces\Data\ConnectionResult;
use App\Marketplaces\Data\ListingData;
use App\Marketplaces\Data\OrderPage;
use App\Marketplaces\Data\RemoteListingPage;
use App\Marketplaces\Data\WebhookResult;
use App\Marketplaces\MarketplaceClient;
use App\Models\Marketplace;
use App\Models\MarketplaceListing;
use App\Models\MarketplaceOrder;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Adapter یک مارکت‌پلیس
 *
 * تفاوت APIها (احراز هویت، آدرس‌ها، قالب داده، واحد مبلغ، کد وضعیت‌ها) فقط داخل Adapter است؛
 * هسته (SyncService / OrderImporter / Jobs / پنل) فقط با این قرارداد و DTOها کار می‌کند.
 * متدهایی که در capabilities() اعلام نشده‌اند فراخوانی نمی‌شوند (پیش‌فرض: UnsupportedOperation).
 */
interface MarketplaceProvider
{
    public function key(): string;

    public function label(): string;

    public function description(): string;

    /** @return string[] ثابت‌های App\Marketplaces\Capability */
    public function capabilities(): array;

    /** محدودیت‌های API رسمی که در پنل نمایش داده می‌شود @return string[] */
    public function limitations(): array;

    /**
     * فیلدهای محرمانه (رمزنگاری می‌شوند)
     *
     * @return array<string, array{label: string, type?: string, required?: bool, help?: string}>
     */
    public function credentialFields(): array;

    /**
     * تنظیمات غیرمحرمانه — type: text | url | number | boolean | select | textarea
     *
     * @return array<string, array{label: string, type?: string, default?: mixed, required?: bool, help?: string, options?: array, group?: string}>
     */
    public function settingFields(): array;

    public function isConfigured(Marketplace $marketplace): bool;

    public function testConnection(Marketplace $marketplace, MarketplaceClient $client): ConnectionResult;

    /** فهرست محصولات/تنوع‌های موجود در مارکت‌پلیس (برای اتصال دستی یا تطبیق با SKU) */
    public function remoteListings(Marketplace $marketplace, MarketplaceClient $client, int $page = 1): RemoteListingPage;

    /** ایجاد محصول در مارکت‌پلیس @return array{external_id: string, external_variant_id?: ?string, external_url?: ?string, meta?: array} */
    public function createListing(Marketplace $marketplace, MarketplaceClient $client, MarketplaceListing $listing, ListingData $data): array;

    /**
     * به‌روزرسانی محصول متصل
     *
     * @param  string[]  $fields  زیرمجموعه content | price | stock | status
     * @return array  مقادیر جدید meta اتصال (مثلاً شناسه تصاویر بارگذاری‌شده)
     */
    public function updateListing(Marketplace $marketplace, MarketplaceClient $client, MarketplaceListing $listing, ListingData $data, array $fields): array;

    public function fetchOrders(Marketplace $marketplace, MarketplaceClient $client, ?string $cursor = null): OrderPage;

    /** به‌روزرسانی یک سفارش ثبت‌شده از مارکت‌پلیس (null = پشتیبانی نمی‌شود/یافت نشد) */
    public function fetchOrder(Marketplace $marketplace, MarketplaceClient $client, MarketplaceOrder $order): ?\App\Marketplaces\Data\RemoteOrder;

    /**
     * عملیات قابل انجام روی سفارش در مارکت‌پلیس
     *
     * @return array<string, array{label: string, fields?: array}>
     */
    public function orderActions(Marketplace $marketplace, MarketplaceOrder $order): array;

    public function performOrderAction(Marketplace $marketplace, MarketplaceClient $client, MarketplaceOrder $order, string $action, array $input): void;

    /** پردازش وب‌هوک ورودی (اعتبار آدرس قبلاً با webhook_secret بررسی شده است) */
    public function handleWebhook(Marketplace $marketplace, MarketplaceClient $client, Request $request): WebhookResult;

    /** پاسخ به درخواست خوراک محصولات (مارکت‌پلیس‌هایی که از فروشگاه Pull می‌کنند) */
    public function feed(Marketplace $marketplace, MarketplaceClient $client, Request $request): Response;
}
