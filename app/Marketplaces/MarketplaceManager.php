<?php

namespace App\Marketplaces;

use App\Marketplaces\Contracts\MarketplaceProvider;
use App\Models\Marketplace;
use InvalidArgumentException;

/**
 * رجیستری Adapterهای مارکت‌پلیس (config/marketplaces.php)
 */
class MarketplaceManager
{
    /** @var array<string, MarketplaceProvider> */
    protected array $providers = [];

    public function has(string $key): bool
    {
        return array_key_exists($key, (array) config('marketplaces.providers', []));
    }

    public function provider(string $key): MarketplaceProvider
    {
        $class = config("marketplaces.providers.{$key}");

        if (! $class || ! is_subclass_of($class, MarketplaceProvider::class)) {
            throw new InvalidArgumentException("مارکت‌پلیس «{$key}» تعریف نشده است.");
        }

        return $this->providers[$key] ??= app($class);
    }

    /** @return array<string, MarketplaceProvider> */
    public function providers(): array
    {
        return collect(array_keys((array) config('marketplaces.providers', [])))
            ->mapWithKeys(fn ($key) => [$key => $this->provider($key)])
            ->all();
    }

    public function client(Marketplace $marketplace, array $context = []): MarketplaceClient
    {
        return (new MarketplaceClient($marketplace))->withContext($context);
    }

    /** آدرس وب‌هوک ورودی این مارکت‌پلیس */
    public function webhookUrl(Marketplace $marketplace): string
    {
        return route('marketplaces.webhook', ['provider' => $marketplace->provider, 'secret' => (string) $marketplace->webhook_secret]);
    }

    /** آدرس خوراک محصولات (برای مارکت‌پلیس‌هایی که از فروشگاه Pull می‌کنند) */
    public function feedUrl(Marketplace $marketplace): string
    {
        return route('marketplaces.feed', ['provider' => $marketplace->provider]);
    }
}
