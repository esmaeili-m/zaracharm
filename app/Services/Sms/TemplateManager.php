<?php

namespace App\Services\Sms;

use App\Models\Setting;
use App\Models\SmsTemplate;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * همگام‌سازی، ثبت و تعیین وضعیت الگوها
 *
 * مستندات ملی پیامک معنای عددی BodyStatus را منتشر نکرده است؛ بنابراین نگاشت «کد خام → وضعیت»
 * توسط مدیر (با مقایسه با پنل ملی پیامک) در تنظیمات sms_body_status_map ذخیره می‌شود.
 * کد نگاشت‌نشده همیشه «وضعیت نامشخص» است و الگو قابل ارسال نیست.
 */
class TemplateManager
{
    public const STATUS_MAP_KEY = 'sms_body_status_map';

    public const MAPPABLE = ['approved', 'pending', 'rejected'];

    public function __construct(protected MelipayamakClient $client)
    {
    }

    /** @return array<int, string> */
    public function statusMap(): array
    {
        $map = json_decode((string) Setting::option(self::STATUS_MAP_KEY, '{}'), true);

        return collect(is_array($map) ? $map : [])
            ->filter(fn ($status) => in_array($status, self::MAPPABLE, true))
            ->mapWithKeys(fn ($status, $code) => [(int) $code => $status])
            ->all();
    }

    /** @param array<int|string, string|null> $map */
    public function saveStatusMap(array $map): void
    {
        $clean = collect($map)
            ->filter(fn ($status, $code) => is_numeric($code) && in_array($status, self::MAPPABLE, true))
            ->mapWithKeys(fn ($status, $code) => [(string) (int) $code => $status])
            ->all();

        Setting::updateOrCreate(['key' => self::STATUS_MAP_KEY], ['value' => json_encode($clean)]);
        Cache::forget('settings');

        // اعمال روی الگوهای موجود
        SmsTemplate::whereNotNull('provider_status')->get()->each(function (SmsTemplate $template) use ($clean) {
            $status = $clean[(string) $template->provider_status] ?? 'unknown';

            if ($template->status !== $status && ! in_array($template->status, ['missing'], true)) {
                $template->update(['status' => $status]);
            }
        });
    }

    public function mapStatus(int $code): string
    {
        return $this->statusMap()[$code] ?? 'unknown';
    }

    /**
     * دریافت الگوها از ملی پیامک و به‌روزرسانی جدول محلی
     *
     * @return array{total: int, created: int, updated: int, missing: int}
     */
    public function sync(): array
    {
        $remote = collect($this->client->bodies())->keyBy('id');
        $stats = ['total' => $remote->count(), 'created' => 0, 'updated' => 0, 'missing' => 0];

        DB::transaction(function () use ($remote, &$stats) {
            $local = SmsTemplate::whereNotNull('provider_body_id')->get()->keyBy('provider_body_id');

            foreach ($remote as $id => $item) {
                $attributes = [
                    'provider_status' => $item['status'],
                    'provider_description' => $item['description'] ? mb_substr($item['description'], 0, 255) : null,
                    'provider_created_at' => $item['created'],
                    'status' => $this->mapStatus($item['status']),
                    'last_checked_at' => now(),
                    'last_error' => null,
                ];

                if ($template = $local->get($id)) {
                    // متن مرجع همیشه متن ثبت‌شده در ملی پیامک است
                    $template->update($attributes + ['body' => $item['body']]);
                    $stats['updated']++;
                } else {
                    SmsTemplate::create($attributes + [
                        'title' => $item['title'] !== '' ? $item['title'] : 'الگوی ' . $id,
                        'body' => $item['body'],
                        'provider_body_id' => $id,
                        'category' => 'service',
                    ]);
                    $stats['created']++;
                }
            }

            $stats['missing'] = SmsTemplate::whereNotNull('provider_body_id')
                ->whereNotIn('provider_body_id', $remote->keys()->all() ?: [0])
                ->update(['status' => 'missing', 'last_checked_at' => now()]);
        });

        return $stats;
    }

    /**
     * ارسال الگوی محلی برای ثبت (یا ویرایش متن الگوی ردشده) در ملی پیامک
     * موفقیت فقط وقتی ثبت می‌شود که الگو در فهرست واقعی سرویس پیدا شود.
     *
     * @return array{ok: bool, message: string}
     */
    public function submit(SmsTemplate $template): array
    {
        if ($errors = TemplateBody::errors($template->body)) {
            return ['ok' => false, 'message' => implode(' ', $errors)];
        }

        if ($template->provider_body_id) {
            $submitted = $template->body;
            $raw = $this->client->editBody((int) $template->provider_body_id, $submitted);
            $this->sync(); // متن محلی با متن واقعی سرویس جایگزین می‌شود
            $template->refresh();

            $ok = $template->status !== 'missing' && trim($template->body) === trim($submitted);
            $template->update([
                'submitted_at' => now(),
                'last_error' => $ok ? null : 'ویرایش متن در ملی پیامک اعمال نشد (خروجی سرویس: ' . $raw . ')',
            ]);

            return [
                'ok' => $ok,
                'message' => $ok
                    ? 'متن الگو برای بررسی دوباره ارسال شد. وضعیت فعلی: ' . $template->statusBadge()['title']
                    : 'ویرایش در ملی پیامک تأیید نشد (خروجی سرویس: ' . $raw . ').',
            ];
        }

        $raw = $this->client->addBody($template->title, $template->body);

        // یافتن الگوی ثبت‌شده در فهرست واقعی (جدیدترین الگوی هم‌متن که به الگوی دیگری متصل نیست)
        $linked = SmsTemplate::whereNotNull('provider_body_id')->pluck('provider_body_id')->all();
        $match = collect($this->client->bodies())
            ->filter(fn ($item) => trim($item['body']) === trim($template->body) && ! in_array($item['id'], $linked))
            ->sortByDesc('id')
            ->first();

        if (! $match) {
            $template->update([
                'status' => 'failed',
                'submitted_at' => now(),
                'last_error' => 'الگو در فهرست ملی پیامک یافت نشد (خروجی سرویس: ' . $raw . ')',
            ]);

            return ['ok' => false, 'message' => $template->last_error];
        }

        $template->update([
            'provider_body_id' => $match['id'],
            'provider_status' => $match['status'],
            'provider_description' => $match['description'],
            'provider_created_at' => $match['created'],
            'status' => $this->mapStatus($match['status']),
            'submitted_at' => now(),
            'last_checked_at' => now(),
            'last_error' => null,
        ]);

        return ['ok' => true, 'message' => 'الگو با کد ' . $match['id'] . ' در ملی پیامک ثبت شد. وضعیت: ' . $template->statusBadge()['title']];
    }
}
