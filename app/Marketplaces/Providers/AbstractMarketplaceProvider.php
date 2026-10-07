<?php

namespace App\Marketplaces\Providers;

use App\Marketplaces\Capability;
use App\Marketplaces\Contracts\MarketplaceProvider;
use App\Marketplaces\Data\ConnectionResult;
use App\Marketplaces\Data\ListingData;
use App\Marketplaces\Data\OrderPage;
use App\Marketplaces\Data\RemoteListingPage;
use App\Marketplaces\Data\RemoteOrder;
use App\Marketplaces\Data\WebhookResult;
use App\Marketplaces\Exceptions\UnsupportedOperationException;
use App\Marketplaces\MarketplaceClient;
use App\Models\Marketplace;
use App\Models\MarketplaceListing;
use App\Models\MarketplaceOrder;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * پیاده‌سازی مشترک Adapterها
 *
 * - تنظیمات عمومی قیمت/موجودی/زمان‌بندی بر اساس قابلیت‌ها به فرم پنل اضافه می‌شود
 * - عملیات پشتیبانی‌نشده به‌صورت پیش‌فرض UnsupportedOperationException می‌دهند
 * - ابزار تبدیل واحد مبلغ و خواندن مقاوم داده
 */
abstract class AbstractMarketplaceProvider implements MarketplaceProvider
{
    /** تنظیمات اختصاصی این Adapter */
    abstract protected function providerSettingFields(): array;

    public function limitations(): array
    {
        return [];
    }

    final public function settingFields(): array
    {
        return $this->providerSettingFields() + $this->commonSettingFields();
    }

    /** تنظیمات عمومی (گروه «قیمت و موجودی» / «زمان‌بندی») */
    protected function commonSettingFields(): array
    {
        $caps = $this->capabilities();
        $fields = [];

        if (array_intersect($caps, [Capability::UPDATE_PRICE, Capability::CREATE_LISTING, Capability::PRODUCT_FEED])) {
            $fields += [
                'use_final_price' => [
                    'label' => 'ارسال قیمت نهایی (با اعمال تخفیف‌ها و کمپین‌های فروشگاه)',
                    'type' => 'boolean',
                    'default' => true,
                    'group' => 'قیمت و موجودی',
                ],
                'price_markup_percent' => [
                    'label' => 'افزایش قیمت در این مارکت‌پلیس (درصد)',
                    'type' => 'number',
                    'default' => 0,
                    'help' => 'برای پوشش کمیسیون؛ ۰ = همان قیمت فروشگاه',
                    'group' => 'قیمت و موجودی',
                ],
                'price_rounding' => [
                    'label' => 'گرد کردن قیمت به بالا (تومان)',
                    'type' => 'number',
                    'default' => 0,
                    'help' => 'مثلاً ۱۰۰۰؛ ۰ = بدون گرد کردن',
                    'group' => 'قیمت و موجودی',
                ],
            ];
        }

        if (array_intersect($caps, [Capability::UPDATE_STOCK, Capability::CREATE_LISTING, Capability::PRODUCT_FEED])) {
            $fields += [
                'stock_buffer' => [
                    'label' => 'ذخیره اطمینان موجودی',
                    'type' => 'number',
                    'default' => 0,
                    'help' => 'این تعداد از موجودی قابل فروش کم می‌شود تا هم‌زمانی فروش باعث فروش بیش از موجودی نشود.',
                    'group' => 'قیمت و موجودی',
                ],
                'max_stock' => [
                    'label' => 'سقف موجودی اعلام‌شده',
                    'type' => 'number',
                    'default' => 0,
                    'help' => '۰ = بدون سقف',
                    'group' => 'قیمت و موجودی',
                ],
            ];
        }

        if (in_array(Capability::PULL_ORDERS, $caps, true)) {
            $fields += [
                'order_pull_minutes' => [
                    'label' => 'فاصله دریافت خودکار سفارش‌ها (دقیقه)',
                    'type' => 'number',
                    'default' => 10,
                    'group' => 'زمان‌بندی',
                ],
                'deduct_stock_on_order' => [
                    'label' => 'کسر خودکار موجودی انبار فروشگاه با ثبت سفارش',
                    'type' => 'boolean',
                    'default' => true,
                    'group' => 'زمان‌بندی',
                ],
            ];
        }

        return $fields;
    }

    public function isConfigured(Marketplace $marketplace): bool
    {
        foreach ($this->credentialFields() as $key => $field) {
            if (($field['required'] ?? false) && blank($marketplace->credential($key))) {
                return false;
            }
        }

        foreach ($this->settingFields() as $key => $field) {
            if (($field['required'] ?? false) && blank($marketplace->setting($key))) {
                return false;
            }
        }

        return true;
    }

    public function remoteListings(Marketplace $marketplace, MarketplaceClient $client, int $page = 1): RemoteListingPage
    {
        throw new UnsupportedOperationException('دریافت فهرست محصولات در API این مارکت‌پلیس پشتیبانی نمی‌شود.');
    }

    public function createListing(Marketplace $marketplace, MarketplaceClient $client, MarketplaceListing $listing, ListingData $data): array
    {
        throw new UnsupportedOperationException('ایجاد محصول از طریق API این مارکت‌پلیس امکان‌پذیر نیست.');
    }

    public function updateListing(Marketplace $marketplace, MarketplaceClient $client, MarketplaceListing $listing, ListingData $data, array $fields): array
    {
        throw new UnsupportedOperationException('به‌روزرسانی محصول از طریق API این مارکت‌پلیس امکان‌پذیر نیست.');
    }

    public function fetchOrders(Marketplace $marketplace, MarketplaceClient $client, ?string $cursor = null): OrderPage
    {
        throw new UnsupportedOperationException('دریافت سفارش در API این مارکت‌پلیس پشتیبانی نمی‌شود.');
    }

    public function fetchOrder(Marketplace $marketplace, MarketplaceClient $client, MarketplaceOrder $order): ?RemoteOrder
    {
        return null;
    }

    public function orderActions(Marketplace $marketplace, MarketplaceOrder $order): array
    {
        return [];
    }

    public function performOrderAction(Marketplace $marketplace, MarketplaceClient $client, MarketplaceOrder $order, string $action, array $input): void
    {
        throw new UnsupportedOperationException('تغییر وضعیت سفارش از طریق API این مارکت‌پلیس پشتیبانی نمی‌شود.');
    }

    public function handleWebhook(Marketplace $marketplace, MarketplaceClient $client, Request $request): WebhookResult
    {
        return WebhookResult::ignored('وب‌هوک برای این مارکت‌پلیس پشتیبانی نمی‌شود.');
    }

    public function feed(Marketplace $marketplace, MarketplaceClient $client, Request $request): Response
    {
        throw new UnsupportedOperationException('خوراک محصولات برای این مارکت‌پلیس تعریف نشده است.');
    }

    // ---------------------------------------------------------------- ابزارها

    protected function baseUrl(Marketplace $marketplace): string
    {
        return rtrim((string) $marketplace->setting('base_url'), '/');
    }

    /** مبلغ تومان => واحد مارکت‌پلیس */
    protected function toRemoteAmount(Marketplace $marketplace, int $toman): int
    {
        return $marketplace->setting('price_unit') === 'rial' ? $toman * 10 : $toman;
    }

    /** مبلغ مارکت‌پلیس => تومان */
    protected function toLocalAmount(Marketplace $marketplace, $amount): int
    {
        $amount = (int) round((float) $amount);

        return $marketplace->setting('price_unit') === 'rial' ? intdiv($amount, 10) : $amount;
    }

    /** اولین مقدار غیرخالی از بین کلیدها (با پشتیبانی از dot-notation) */
    protected function pick(array $data, array $keys, $default = null)
    {
        foreach ($keys as $key) {
            $value = data_get($data, $key);

            if ($value !== null && $value !== '') {
                return $value;
            }
        }

        return $default;
    }

    protected function date($value): ?CarbonInterface
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return is_numeric($value)
                ? Carbon::createFromTimestamp((int) ($value > 9999999999 ? $value / 1000 : $value))
                : Carbon::parse($value);
        } catch (Throwable) {
            return null;
        }
    }

    protected function unitOptions(string $default): array
    {
        return [
            'label' => 'واحد مبلغ در API',
            'type' => 'select',
            'default' => $default,
            'options' => ['rial' => 'ریال', 'toman' => 'تومان'],
            'group' => 'اتصال',
        ];
    }

    /** محدودیت‌ها + اتصال پایه */
    protected function connectionFailure(Throwable $e): ConnectionResult
    {
        return ConnectionResult::failure($e->getMessage());
    }
}
