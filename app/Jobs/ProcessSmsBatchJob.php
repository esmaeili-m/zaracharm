<?php

namespace App\Jobs;

use App\Services\Sms\SmsSender;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * پردازش یک تکه از پیامک‌های یک عملیات ارسال؛ تا خالی شدن صف عملیات، Job بعدی صف می‌شود.
 * تلاش مجدد خودکار Job وجود ندارد (tries=1) تا پیامکی دوبار ارسال نشود؛
 * پیامک‌های گیرکرده را sms:sync بازیابی می‌کند.
 */
class ProcessSmsBatchJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 600;

    public function __construct(public int $batchId)
    {
    }

    public function handle(SmsSender $sender): void
    {
        if ($sender->processBatch($this->batchId)) {
            static::dispatch($this->batchId);
        }
    }
}
