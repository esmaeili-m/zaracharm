<?php

namespace App\Marketplaces\Providers;

use App\Marketplaces\Capability;
use App\Marketplaces\Data\ConnectionResult;
use App\Marketplaces\MarketplaceClient;
use App\Marketplaces\Services\ListingBuilder;
use App\Models\Marketplace;
use App\Models\MarketplaceListing;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * ترب — موتور مقایسه قیمت (نه فروشگاه)
 *
 * ترب سفارش و پرداخت ندارد و API برای ارسال (Push) اطلاعات به ترب وجود ندارد؛ ترب خودش
 * اطلاعات محصولات را از «API محصولات فروشگاه» (نسخه ۳) می‌خواند:
 *
 *   POST {feed_url}   هدرها: X-Torob-Token (JWT امضاشده توسط ترب) ، X-Torob-Token-Version: 1
 *   بدنه یکی از:   {"page_urls": [...]}  |  {"page_uniques": [...]}  |  {"page": 1, "sort": "date_added_desc" | "date_updated_desc"}
 *   پاسخ:         {"api_version": "1.0", "current_page", "count", "max_pages", "products": [...]}
 *
 * بنابراین «همگام‌سازی» ترب یعنی پاسخ دقیق و به‌روز به همین درخواست‌ها؛ قیمت و موجودی هر بار
 * لحظه‌ای از انبار و قیمت‌گذاری فروشگاه محاسبه می‌شود.
 */
class TorobProvider extends AbstractMarketplaceProvider
{
    protected const PER_PAGE = 100;

    public function key(): string
    {
        return 'torob';
    }

    public function label(): string
    {
        return 'ترب';
    }

    public function description(): string
    {
        return 'ارائه API محصولات (نسخه ۳) برای ترب؛ قیمت، موجودی، تصاویر و مشخصات به‌صورت لحظه‌ای از فروشگاه خوانده می‌شود.';
    }

    public function capabilities(): array
    {
        return [Capability::PRODUCT_FEED];
    }

    public function limitations(): array
    {
        return [
            'ترب سفارش، پرداخت و تراکنش ندارد؛ کاربر برای خرید به صفحه محصول فروشگاه هدایت می‌شود.',
            'API برای ارسال اطلاعات به ترب وجود ندارد؛ ترب به‌صورت دوره‌ای آدرس API فروشگاه را فراخوانی می‌کند و زمان آن را ترب تعیین می‌کند.',
            'آدرس API و دامنه فروشگاه باید در پنل فروشندگان ترب ثبت و توسط ترب فعال شود.',
        ];
    }

    public function credentialFields(): array
    {
        return [
            'public_key' => [
                'label' => 'کلید عمومی ترب (PEM)',
                'type' => 'textarea',
                'help' => 'کلیدی که ترب برای بررسی امضای X-Torob-Token در اختیار فروشگاه قرار می‌دهد.',
            ],
        ];
    }

    protected function providerSettingFields(): array
    {
        return [
            'require_token' => [
                'label' => 'الزام توکن ترب (X-Torob-Token)',
                'type' => 'boolean',
                'default' => true,
                'help' => 'با فعال بودن، فقط درخواست‌های امضاشده توسط ترب پاسخ داده می‌شوند.',
                'group' => 'اتصال',
            ],
            'token_audience' => [
                'label' => 'دامنه فروشگاه (aud توکن)',
                'type' => 'text',
                'default' => parse_url((string) config('app.url'), PHP_URL_HOST) ?: '',
                'group' => 'اتصال',
            ],
            'price_unit' => $this->unitOptions('toman'),
            'include_all_products' => [
                'label' => 'ارائه همه محصولات فعال فروشگاه',
                'type' => 'boolean',
                'default' => true,
                'help' => 'غیرفعال = فقط محصولاتی که در «اتصال محصولات» برای ترب فعال شده‌اند.',
                'group' => 'محصول',
            ],
            'per_variant' => [
                'label' => 'هر واریانت به‌عنوان یک کالای جدا',
                'type' => 'boolean',
                'default' => false,
                'help' => 'غیرفعال = هر محصول یک کالا با کمترین قیمت موجود (مناسب صفحه محصول با انتخاب تنوع).',
                'group' => 'محصول',
            ],
        ];
    }

    public function isConfigured(Marketplace $marketplace): bool
    {
        return ! $marketplace->setting('require_token', true) || filled($marketplace->credential('public_key'));
    }

    public function testConnection(Marketplace $marketplace, MarketplaceClient $client): ConnectionResult
    {
        if ($marketplace->setting('require_token', true)) {
            $key = $this->publicKey($marketplace);

            if (! $key) {
                return ConnectionResult::failure('کلید عمومی ترب وارد نشده یا معتبر نیست.');
            }

            if ($key['type'] === 'ed25519' && ! $this->ed25519Available()) {
                return ConnectionResult::failure('کلید ترب از نوع Ed25519 است و افزونه sodium در PHP سرور فعال نیست (extension=sodium در php.ini).');
            }
        }

        $count = $this->productQuery($marketplace)->count();

        return ConnectionResult::success("API محصولات آماده است ({$count} محصول). آدرس را در پنل ترب ثبت کنید.");
    }

    // ---------------------------------------------------------------- خوراک محصولات

    public function feed(Marketplace $marketplace, MarketplaceClient $client, Request $request): Response
    {
        if ($marketplace->setting('require_token', true) && ($error = $this->verifyToken($marketplace, $request)) !== null) {
            $client->log('feed', 'failed', 'توکن ترب نامعتبر: ' . $error, ['ip' => $request->ip()], 'in');

            return response()->json(['error' => 'unauthorized'], 401);
        }

        $body = (array) $request->json()->all() ?: $request->all();
        $query = $this->productQuery($marketplace);
        $page = 1;

        if (! empty($body['page_uniques']) && is_array($body['page_uniques'])) {
            $ids = collect($body['page_uniques'])->map(fn ($u) => (int) strtok((string) $u, '-'))->filter()->unique()->take(self::PER_PAGE);
            $query->whereIn('id', $ids);
        } elseif (! empty($body['page_urls']) && is_array($body['page_urls'])) {
            $slugs = collect($body['page_urls'])->map(function ($url) {
                $path = rawurldecode((string) parse_url((string) $url, PHP_URL_PATH));

                return preg_match('#/products/([^/?]+)#u', $path, $m) ? $m[1] : null;
            })->filter()->unique()->take(self::PER_PAGE);
            $query->whereIn('slug', $slugs);
        } else {
            $page = max(1, (int) ($body['page'] ?? $request->query('page', 1)));
            $sort = (string) ($body['sort'] ?? 'date_added_desc');
            $query->orderByDesc($sort === 'date_updated_desc' ? 'updated_at' : 'created_at')->orderByDesc('id');
        }

        $total = (clone $query)->count();
        $products = $query->forPage($page, self::PER_PAGE)->get();

        $builder = app(ListingBuilder::class);
        $items = [];

        foreach ($products as $product) {
            array_push($items, ...$this->productItems($marketplace, $builder, $product));
        }

        $marketplace->forceFill(['last_product_sync_at' => now(), 'last_stock_sync_at' => now()])->save();
        $client->log('feed', 'success', 'ارائه ' . count($items) . ' کالا به ترب (صفحه ' . $page . ')', [
            'request' => array_intersect_key($body, array_flip(['page', 'sort', 'page_urls', 'page_uniques'])),
        ], 'in');

        return response()->json([
            'api_version' => '1.0',
            'current_page' => $page,
            'count' => $total,
            'max_pages' => (int) max(1, ceil($total / self::PER_PAGE)),
            'products' => $items,
        ]);
    }

    protected function productQuery(Marketplace $marketplace): Builder
    {
        $query = Product::query()
            ->published()
            ->whereHas('variants', fn ($q) => $q->where('status', true))
            ->with(['variants' => fn ($q) => $q->where('status', true), 'variants.optionValues.optionValue', 'variants.inventoryItems.inventory', 'media', 'primaryCategory', 'specifications']);

        if (! $marketplace->setting('include_all_products', true)) {
            $query->whereIn('id', MarketplaceListing::where('marketplace_id', $marketplace->id)->active()->select('product_id'));
        }

        return $query;
    }

    /** @return array<int, array> */
    protected function productItems(Marketplace $marketplace, ListingBuilder $builder, Product $product): array
    {
        $variants = $product->variants;

        if (! $marketplace->setting('include_all_products', true)) {
            $allowed = MarketplaceListing::where('marketplace_id', $marketplace->id)->active()->where('product_id', $product->id)->pluck('product_variant_id')->all();
            $variants = $variants->whereIn('id', $allowed);
        }

        $listings = $variants->map(fn ($variant) => $builder->build($marketplace, $variant))->filter(fn ($d) => $d->price > 0)->values();

        if ($listings->isEmpty()) {
            return [];
        }

        $format = fn ($data, string $unique, ?string $subtitle) => array_filter([
            'page_unique' => $unique,
            'page_url' => $data->url,
            'product_group_id' => $marketplace->setting('per_variant') ? (string) $product->id : null,
            'title' => $data->title,
            'subtitle' => $subtitle,
            'current_price' => $this->toRemoteAmount($marketplace, $data->price),
            'old_price' => $data->comparePrice && $data->comparePrice > $data->price ? $this->toRemoteAmount($marketplace, $data->comparePrice) : null,
            'availability' => $data->active && $data->stock > 0 ? 'instock' : 'outofstock',
            'category_name' => $data->categoryName,
            'image_links' => array_column($data->images, 'url'),
            'short_desc' => $data->brief ? mb_substr($data->brief, 0, 500) : null,
            'spec' => $data->specs ?: null,
            'date_added' => optional($product->created_at)->toIso8601String(),
            'date_updated' => optional($product->updated_at)->toIso8601String(),
        ], fn ($v) => $v !== null && $v !== []);

        if ($marketplace->setting('per_variant')) {
            return $listings->map(fn ($data) => $format($data, $product->id . '-' . $data->variantId, $data->variantTitle))->all();
        }

        // یک کالا برای محصول: کمترین قیمتِ موجود (یا کمترین قیمت در صورت ناموجودی کامل)
        $inStock = $listings->filter(fn ($d) => $d->active && $d->stock > 0);
        $best = ($inStock->isNotEmpty() ? $inStock : $listings)->sortBy('price')->first();

        return [$format($best, (string) $product->id, null)];
    }

    // ---------------------------------------------------------------- اعتبارسنجی توکن ترب

    /** null = معتبر ، رشته = دلیل رد */
    protected function verifyToken(Marketplace $marketplace, Request $request): ?string
    {
        $jwt = (string) $request->header('X-Torob-Token');

        if ($jwt === '') {
            return 'هدر X-Torob-Token ارسال نشده است.';
        }

        $parts = explode('.', $jwt);

        if (count($parts) !== 3) {
            return 'ساختار توکن نامعتبر است.';
        }

        [$h, $p, $s] = $parts;
        $header = json_decode($this->b64($h), true);
        $claims = json_decode($this->b64($p), true);
        $signature = $this->b64($s);

        if (! is_array($header) || ! is_array($claims) || $signature === '') {
            return 'توکن قابل خواندن نیست.';
        }

        $key = $this->publicKey($marketplace);

        if (! $key) {
            return 'کلید عمومی ترب تنظیم نشده یا نامعتبر است.';
        }

        if ($key['type'] === 'ed25519' && ! $this->ed25519Available()) {
            return 'افزونه sodium برای بررسی امضای Ed25519 فعال نیست.';
        }

        if (! $this->verifySignature((string) ($header['alg'] ?? ''), "{$h}.{$p}", $signature, $key)) {
            return 'امضای توکن معتبر نیست.';
        }

        $now = time();
        $leeway = 60;

        if (isset($claims['exp']) && $now - $leeway > (int) $claims['exp']) {
            return 'توکن منقضی شده است.';
        }

        if (isset($claims['nbf']) && $now + $leeway < (int) $claims['nbf']) {
            return 'توکن هنوز معتبر نیست.';
        }

        $audience = strtolower((string) $marketplace->setting('token_audience'));

        if ($audience !== '' && isset($claims['aud'])) {
            $hosts = collect((array) $claims['aud'])->map(fn ($aud) => strtolower((string) (parse_url((string) $aud, PHP_URL_HOST) ?: $aud)));
            $expected = strtolower((string) (parse_url($audience, PHP_URL_HOST) ?: $audience));

            if (! $hosts->contains(fn ($host) => $host === $expected || $host === 'www.' . $expected || 'www.' . $host === $expected)) {
                return 'دامنه توکن (aud) با فروشگاه مطابقت ندارد.';
            }
        }

        return null;
    }

    /** @return array{type: string, key: mixed}|null */
    protected function publicKey(Marketplace $marketplace): ?array
    {
        $pem = trim((string) $marketplace->credential('public_key'));

        if ($pem === '') {
            return null;
        }

        if (! str_contains($pem, 'BEGIN')) {
            $pem = "-----BEGIN PUBLIC KEY-----\n" . chunk_split($pem, 64, "\n") . "-----END PUBLIC KEY-----";
        }

        // Ed25519: ۳۲ بایت آخر SubjectPublicKeyInfo
        $der = base64_decode(preg_replace('/-----[^-]+-----|\s+/', '', $pem), true);

        if ($der !== false && strlen($der) === 44 && str_starts_with(bin2hex($der), '302a300506032b6570')) {
            return ['type' => 'ed25519', 'key' => substr($der, -32)];
        }

        try {
            $key = openssl_pkey_get_public($pem);
        } catch (Throwable) {
            $key = false;
        }

        return $key ? ['type' => 'openssl', 'key' => $key] : null;
    }

    protected function verifySignature(string $alg, string $input, string $signature, array $key): bool
    {
        try {
            if ($alg === 'EdDSA') {
                // ext-sodium یا paragonie/sodium_compat (همان تابع را تعریف می‌کند)
                return $key['type'] === 'ed25519' && $this->ed25519Available()
                    && sodium_crypto_sign_verify_detached($signature, $input, $key['key']);
            }

            if ($key['type'] !== 'openssl') {
                return false;
            }

            $algo = match ($alg) {
                'RS256', 'ES256' => OPENSSL_ALGO_SHA256,
                'RS384', 'ES384' => OPENSSL_ALGO_SHA384,
                'RS512', 'ES512' => OPENSSL_ALGO_SHA512,
                default => null,
            };

            if ($algo === null) {
                return false; // الگوریتم متقارن/ناشناخته پذیرفته نمی‌شود
            }

            if (str_starts_with($alg, 'ES')) {
                $signature = $this->ecdsaToDer($signature);
            }

            return openssl_verify($input, $signature, $key['key'], $algo) === 1;
        } catch (Throwable) {
            return false;
        }
    }

    protected function ed25519Available(): bool
    {
        return function_exists('sodium_crypto_sign_verify_detached');
    }

    /** امضای JOSE (r||s) => DER برای openssl */
    protected function ecdsaToDer(string $signature): string
    {
        $half = intdiv(strlen($signature), 2);
        $int = function (string $bytes): string {
            $bytes = ltrim($bytes, "\x00");
            if ($bytes === '' || ord($bytes[0]) > 0x7F) {
                $bytes = "\x00" . $bytes;
            }

            return "\x02" . chr(strlen($bytes)) . $bytes;
        };
        $seq = $int(substr($signature, 0, $half)) . $int(substr($signature, $half));

        return "\x30" . chr(strlen($seq)) . $seq;
    }

    protected function b64(string $value): string
    {
        return (string) base64_decode(strtr($value, '-_', '+/') . str_repeat('=', (4 - strlen($value) % 4) % 4));
    }
}
