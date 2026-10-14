<?php

namespace App\Services\Sms;

use App\Jobs\ProcessSmsBatchJob;
use App\Models\SmsBatch;
use App\Models\SmsMessage;
use App\Models\SmsTemplate;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * ایجاد و پردازش عملیات ارسال
 *
 * جلوگیری از ارسال تکراری:
 *  - uuid یکتای فرم برای هر عملیات (دوبار کلیک/رفرش ← همان عملیات قبلی برگردانده می‌شود)
 *  - هر پیامک پیش از ارسال با UPDATE شرطی (queued → sending) «تصاحب» می‌شود
 *  - قفل پردازش هر عملیات (یک پردازشگر هم‌زمان)
 *  - نتیجه نامعلوم (unknown) هیچ‌وقت خودکار تکرار نمی‌شود
 */
class SmsSender
{
    public function __construct(
        protected SmsGateway $gateway,
        protected AudienceBuilder $audience,
    ) {
    }

    public function prepare(SmsTemplate $template, array $audience, array $variables): array
    {
        $this->ensureSendable($template);

        return $this->audience->build($template, $audience, $variables);
    }

    /**
     * ثبت عملیات و پیامک‌ها و شروع ارسال
     * فهرست گیرندگان دوباره در سرور ساخته می‌شود (به پیش‌نمایش سمت کاربر اعتماد نمی‌شود).
     */
    public function dispatch(SmsTemplate $template, array $audience, array $variables, string $uuid, ?int $userId, array $meta = []): SmsBatch
    {
        if ($existing = SmsBatch::where('uuid', $uuid)->first()) {
            return $existing;
        }

        $plan = $this->prepare($template, $audience, $variables);

        if (! $plan['ready']) {
            throw new RuntimeException('هیچ گیرنده قابل ارسالی وجود ندارد.');
        }

        try {
            $batch = DB::transaction(function () use ($template, $audience, $plan, $uuid, $userId, $meta) {
                $batch = SmsBatch::create([
                    'uuid' => $uuid,
                    'sms_template_id' => $template->id,
                    'template_title' => $template->title,
                    'template_body' => $template->body,
                    'provider_body_id' => $template->provider_body_id,
                    'category' => $template->category,
                    'source' => $audience['source'],
                    'source_meta' => $meta ?: null,
                    'status' => 'queued',
                    'is_fake' => ! $this->gateway->isLive(),
                    'total' => count($plan['ready']),
                    'skipped' => array_sum($plan['skipped']),
                    'skipped_summary' => array_filter($plan['skipped']) ?: null,
                    'estimated_cost' => $plan['estimated_cost'],
                    'created_by' => $userId,
                ]);

                $now = now();
                foreach (array_chunk($plan['ready'], 500) as $chunk) {
                    SmsMessage::insert(array_map(fn ($r) => [
                        'sms_batch_id' => $batch->id,
                        'mobile' => $r['mobile'],
                        'recipient_name' => $r['name'],
                        'user_id' => $r['user_id'],
                        'sms_contact_id' => $r['contact_id'],
                        'args' => json_encode($r['args'], JSON_UNESCAPED_UNICODE),
                        'status' => 'queued',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ], $chunk));
                }

                return $batch;
            });
        } catch (UniqueConstraintViolationException) {
            // درخواست هم‌زمان با همان کلید
            return SmsBatch::where('uuid', $uuid)->firstOrFail();
        }

        SmsAudit::log('batch.create', $batch, [
            'template' => $template->title,
            'source' => $audience['source'],
            'total' => $batch->total,
            'skipped' => $batch->skipped,
            'fake' => $batch->is_fake,
        ]);

        $this->queue($batch);

        return $batch->refresh();
    }

    /** ارسال تکی بلافاصله (بدون انتظار برای صف)؛ گروهی در صف */
    public function queue(SmsBatch $batch): void
    {
        if ($batch->source === 'single') {
            ProcessSmsBatchJob::dispatchSync($batch->id);
        } else {
            ProcessSmsBatchJob::dispatch($batch->id)->afterCommit();
        }
    }

    /**
     * پردازش یک تکه از پیامک‌های در صف؛ اگر باقی ماند Job بعدی صف می‌شود
     *
     * @return bool آیا پیامک در صف باقی مانده است
     */
    public function processBatch(int $batchId): bool
    {
        $lock = Cache::lock('sms-batch-' . $batchId, 900);

        if (! $lock->get()) {
            return false; // پردازشگر دیگری فعال است
        }

        try {
            $batch = SmsBatch::find($batchId);

            if (! $batch || ! in_array($batch->status, ['queued', 'sending'], true)) {
                return false;
            }

            $batch->update(['status' => 'sending', 'started_at' => $batch->started_at ?? now()]);

            $chunk = max(1, (int) config('sms.batch_chunk', 100));
            $pause = max(0, (int) config('sms.throttle_ms', 150)) * 1000;
            $ids = $batch->messages()->where('status', 'queued')->orderBy('id')->limit($chunk)->pluck('id');

            foreach ($ids as $i => $id) {
                // بررسی لغو در میانه ارسال
                if ($i > 0 && $i % 10 === 0 && SmsBatch::whereKey($batchId)->value('status') === 'cancelled') {
                    return false;
                }

                $message = SmsMessage::find($id);
                $result = $message ? $this->sendMessage($message, (int) $batch->provider_body_id) : null;

                if ($result?->stopBatch) {
                    // خطای حساب/تنظیمات: ادامه ارسال بی‌فایده است؛ باقی‌مانده در صف می‌ماند تا پس از رفع مشکل ادامه داده شود
                    $batch->update(['status' => 'failed', 'last_error' => $result->message, 'finished_at' => now()]);

                    return false;
                }

                if ($pause && $this->gateway->isLive()) {
                    usleep($pause);
                }
            }

            $remaining = $batch->messages()->where('status', 'queued')->exists();

            if (! $remaining) {
                $batch->update(['status' => 'completed', 'finished_at' => now(), 'last_error' => null]);
            }

            return $remaining;
        } finally {
            $lock->release();
        }
    }

    public function sendMessage(SmsMessage $message, int $bodyId): ?SendResult
    {
        // تصاحب اتمی: اگر پردازشگر دیگری برداشته باشد، ارسال نمی‌شود
        $claimed = SmsMessage::whereKey($message->id)->where('status', 'queued')
            ->update(['status' => 'sending', 'attempts' => DB::raw('attempts + 1'), 'updated_at' => now()]);

        if (! $claimed) {
            return null;
        }

        $result = $this->gateway->sendPattern($message->mobile, $bodyId, $message->args ?? []);

        $message->update(match ($result->status) {
            'accepted' => [
                'status' => 'accepted',
                'provider_rec_id' => $result->recId,
                'provider_code' => null,
                'error' => null,
                'error_permanent' => false,
                'sent_at' => now(),
            ],
            'failed' => [
                'status' => 'failed',
                'provider_code' => $result->code,
                'error' => mb_substr((string) $result->message, 0, 255),
                'error_permanent' => $result->permanent,
            ],
            default => [
                'status' => 'unknown',
                'provider_code' => $result->code,
                'error' => mb_substr((string) $result->message, 0, 255),
                'error_permanent' => true,
                'sent_at' => now(),
            ],
        });

        return $result;
    }

    public function cancel(SmsBatch $batch): int
    {
        if (! $batch->isCancellable()) {
            return 0;
        }

        return DB::transaction(function () use ($batch) {
            $batch->update(['status' => 'cancelled', 'cancelled_at' => now(), 'finished_at' => now()]);
            $count = $batch->messages()->where('status', 'queued')->update(['status' => 'cancelled', 'updated_at' => now()]);
            SmsAudit::log('batch.cancel', $batch, ['cancelled_messages' => $count]);

            return $count;
        });
    }

    /**
     * تلاش مجدد کنترل‌شده: فقط خطاهای موقت با تلاش کمتر از سقف (+ ادامه پیامک‌های در صفِ عملیات متوقف)
     */
    public function retry(SmsBatch $batch, ?int $messageId = null): int
    {
        if ($batch->status === 'cancelled') {
            return 0;
        }

        $template = $batch->template;
        if (! $template || ! $template->isApproved() || (int) $template->provider_body_id !== (int) $batch->provider_body_id) {
            throw new RuntimeException('الگوی این ارسال دیگر تأییدشده یا قابل استفاده نیست.');
        }

        $count = $batch->messages()
            ->where('status', 'failed')
            ->where('error_permanent', false)
            ->where('attempts', '<', (int) config('sms.max_attempts', 3))
            ->when($messageId, fn ($q) => $q->whereKey($messageId))
            ->update(['status' => 'queued', 'updated_at' => now()]);

        $queued = $batch->messages()->where('status', 'queued')->count();

        if ($queued) {
            $batch->update(['status' => 'queued', 'finished_at' => null, 'last_error' => null]);
            SmsAudit::log('batch.retry', $batch, ['retried' => $count, 'queued' => $queued]);
            ProcessSmsBatchJob::dispatch($batch->id);
        }

        return $queued;
    }

    /**
     * به‌روزرسانی وضعیت تحویل پیامک‌های پذیرفته‌شده (فقط ارسال‌های واقعی)
     */
    public function syncDeliveries(MelipayamakClient $client, int $limit = 1000): int
    {
        $updated = 0;

        SmsMessage::query()
            ->where('status', 'accepted')
            ->whereNotNull('provider_rec_id')
            ->where('sent_at', '>=', now()->subHours((int) config('sms.delivery_window_hours', 72)))
            ->where('sent_at', '<=', now()->subMinute())
            ->where(fn ($q) => $q->whereNull('delivery_checked_at')->orWhere('delivery_checked_at', '<=', now()->subMinutes(10)))
            ->whereHas('batch', fn ($q) => $q->where('is_fake', false))
            ->orderBy('id')
            ->limit($limit)
            ->get(['id', 'provider_rec_id', 'status'])
            ->chunk(100)
            ->each(function ($messages) use ($client, &$updated) {
                $codes = $client->deliveries($messages->pluck('provider_rec_id')->all());

                foreach ($messages as $message) {
                    $code = $codes[$message->provider_rec_id] ?? null;
                    $attributes = ['delivery_checked_at' => now()];

                    if ($code !== null) {
                        $attributes['delivery_code'] = $code;
                        $status = MelipayamakCodes::deliveryStatus($code);

                        if ($status) {
                            $attributes['status'] = $status;
                            $attributes['delivered_at'] = $status === 'delivered' ? now() : null;
                        }
                        $updated++;
                    }

                    $message->update($attributes);
                }
            });

        return $updated;
    }

    /**
     * پیامک‌هایی که در وضعیت «در حال ارسال» گیر کرده‌اند (توقف ناگهانی پردازشگر) → نامشخص
     * عملیات‌های در حال ارسالی که پیامک در صف دارند دوباره صف می‌شوند.
     */
    public function recoverStale(): void
    {
        SmsMessage::where('status', 'sending')->where('updated_at', '<', now()->subMinutes(15))
            ->update(['status' => 'unknown', 'error' => 'پردازش ارسال قطع شد؛ نتیجه نامشخص است', 'error_permanent' => true]);

        SmsBatch::whereIn('status', ['queued', 'sending'])->where('updated_at', '<', now()->subMinutes(15))
            ->pluck('id')
            ->each(function ($id) {
                if (SmsMessage::where('sms_batch_id', $id)->where('status', 'queued')->exists()) {
                    ProcessSmsBatchJob::dispatch($id);
                } else {
                    SmsBatch::whereKey($id)->update(['status' => 'completed', 'finished_at' => now()]);
                }
            });
    }

    protected function ensureSendable(SmsTemplate $template): void
    {
        if (! $template->isApproved()) {
            throw new RuntimeException('فقط الگوی تأییدشده در ملی پیامک قابل ارسال است.');
        }

        if ($errors = TemplateBody::errors($template->body)) {
            throw new RuntimeException(implode(' ', $errors));
        }
    }
}
