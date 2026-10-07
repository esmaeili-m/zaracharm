<?php

namespace App\Marketplaces\Jobs;

use App\Marketplaces\Exceptions\MarketplaceException;
use App\Marketplaces\Services\SyncService;
use App\Models\Marketplace;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;

/**
 * دریافت سفارش‌های یک مارکت‌پلیس (دستی، زمان‌بندی‌شده یا با وب‌هوک)
 */
class PullOrdersJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $uniqueFor = 300;

    public function __construct(public int $marketplaceId)
    {
    }

    public function uniqueId(): string
    {
        return (string) $this->marketplaceId;
    }

    public function backoff(): array
    {
        return [60, 300];
    }

    public function middleware(): array
    {
        return [(new WithoutOverlapping('marketplace-orders-' . $this->marketplaceId))->releaseAfter(30)->expireAfter(600)];
    }

    public function handle(SyncService $sync): void
    {
        $marketplace = Marketplace::find($this->marketplaceId);

        if (! $marketplace) {
            return;
        }

        try {
            $sync->pullOrders($marketplace, $this->attempts());
        } catch (MarketplaceException $e) {
            if ($e->retryable) {
                throw $e;
            }
            // خطای غیرقابل تکرار (احراز هویت، ...) در لاگ و وضعیت مارکت‌پلیس ثبت شده است
        }
    }
}
