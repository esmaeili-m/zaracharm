<?php

namespace App\Marketplaces\Jobs;

use App\Marketplaces\Exceptions\MarketplaceException;
use App\Marketplaces\Services\SyncService;
use App\Models\MarketplaceListing;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * همگام‌سازی یک اتصال محصول؛ خطای قابل تکرار (شبکه/429/5xx) با تأخیر افزایشی دوباره تلاش می‌شود.
 */
class SyncListingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries;

    public function __construct(
        public int $listingId,
        public ?array $only = null,
        public bool $force = false,
    ) {
        $this->tries = (int) config('marketplaces.retry.tries', 5);
    }

    public function backoff(): array
    {
        return (array) config('marketplaces.retry.backoff', [60, 300, 900, 1800]);
    }

    public function middleware(): array
    {
        // دو همگام‌سازی هم‌زمان روی یک اتصال اجرا نمی‌شود
        return [(new WithoutOverlapping('marketplace-listing-' . $this->listingId))->releaseAfter(20)->expireAfter(300)];
    }

    public function handle(SyncService $sync): void
    {
        $listing = MarketplaceListing::find($this->listingId);

        if (! $listing) {
            return;
        }

        try {
            $sync->syncListing($listing, $this->only, $this->force, $this->attempts());
        } catch (MarketplaceException $e) {
            // retryable: صف دوباره تلاش می‌کند (Retry-After در صورت 429)
            if ($e->retryAfter && $this->attempts() < $this->tries) {
                $this->release($e->retryAfter);

                return;
            }

            throw $e;
        }
    }

    public function failed(?Throwable $e): void
    {
        $listing = MarketplaceListing::find($this->listingId);

        if ($listing && $e) {
            app(SyncService::class)->markFailed($listing, 'پس از ' . $this->tries . ' تلاش: ' . $e->getMessage());
        }
    }
}
