<?php

namespace App\Services\Delivery;

use App\Models\Setting;
use App\Models\ShippingSlot;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * محاسبه تاریخ‌های مجاز ارسال برای Checkout بر اساس «تنظیمات ارسال» داشبورد
 *
 * قوانین (جدول settings با پیشوند delivery_):
 *  - delivery_lead_days     : زودترین ارسال، چند روز بعد از ثبت سفارش
 *  - delivery_window_days   : چند تاریخ قابل انتخاب به مشتری نمایش داده شود
 *  - delivery_cutoff        : ساعت پایان پذیرش سفارش (HH:MM)؛ بعد از آن از روز کاری بعد حساب می‌شود
 *  - delivery_working_days  : روزهای کاری هفته (Carbon dayOfWeek: 0=یکشنبه ... 6=شنبه)
 *  - delivery_cost          : هزینه پیش‌فرض ارسال
 *
 * استثناها (جدول shipping_slots):
 *  - is_holiday = true  => تعطیل / غیرقابل ارسال
 *  - is_holiday = false => روز ارسال اضافه (حتی اگر روز کاری نباشد) با هزینه/برچسب اختصاصی
 *
 * همه محاسبات به وقت تهران انجام می‌شود (app.timezone = UTC است).
 */
class DeliveryScheduleService
{
    public const TIMEZONE = 'Asia/Tehran';

    // حداکثر روزهایی که برای پیدا کردن تاریخ‌های مجاز جلو می‌رویم
    protected const SCAN_LIMIT = 120;

    // ترتیب هفته ایرانی برای فرم داشبورد
    public const WEEKDAYS = [
        6 => 'شنبه',
        0 => 'یکشنبه',
        1 => 'دوشنبه',
        2 => 'سه‌شنبه',
        3 => 'چهارشنبه',
        4 => 'پنجشنبه',
        5 => 'جمعه',
    ];

    public const DEFAULTS = [
        'delivery_lead_days' => 1,
        'delivery_window_days' => 7,
        'delivery_cutoff' => '14:00',
        'delivery_working_days' => [6, 0, 1, 2, 3, 4],
        'delivery_cost' => 0,
    ];

    public const CACHE_KEY = 'delivery_settings';

    /**
     * تنظیمات فعلی (با مقادیر پیش‌فرض)
     */
    public function settings(): array
    {
        $stored = Cache::rememberForever(self::CACHE_KEY, fn () => Setting::query()
            ->where('key', 'like', 'delivery\_%')
            ->pluck('value', 'key')
            ->all());

        $settings = self::DEFAULTS;

        foreach ($stored as $key => $value) {
            if (! array_key_exists($key, self::DEFAULTS)) {
                continue;
            }

            $settings[$key] = match ($key) {
                'delivery_working_days' => array_values(array_map('intval', json_decode((string) $value, true) ?: [])),
                'delivery_cutoff' => (string) $value,
                default => (int) $value,
            };
        }

        $settings['delivery_lead_days'] = max(0, min(60, $settings['delivery_lead_days']));
        $settings['delivery_window_days'] = max(1, min(30, $settings['delivery_window_days']));

        return $settings;
    }

    public function saveSettings(array $values): void
    {
        foreach (self::DEFAULTS as $key => $default) {
            if (! array_key_exists($key, $values)) {
                continue;
            }

            $value = $key === 'delivery_working_days'
                ? json_encode(array_values(array_map('intval', $values[$key])))
                : (string) ($values[$key] ?? '');

            Setting::updateOrCreate(['key' => $key], ['value' => $value]);
        }

        Cache::forget(self::CACHE_KEY);
        Cache::forget('settings');
    }

    public function now(): CarbonImmutable
    {
        return CarbonImmutable::now(self::TIMEZONE);
    }

    /**
     * استثناهای فعال از امروز به بعد [Y-m-d => ShippingSlot]
     */
    protected function exceptions(CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        return ShippingSlot::query()
            ->where('is_active', true)
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->get()
            ->keyBy(fn ($slot) => $slot->date->toDateString());
    }

    protected function isWorkingDay(CarbonImmutable $date, array $settings): bool
    {
        return in_array($date->dayOfWeek, $settings['delivery_working_days'], true);
    }

    /**
     * آیا در این تاریخ ارسال انجام می‌شود؟ (روز کاری یا روز اضافه، و تعطیل نباشد)
     */
    protected function isDeliverable(CarbonImmutable $date, array $settings, Collection $exceptions): bool
    {
        $exception = $exceptions->get($date->toDateString());

        if ($exception) {
            return ! $exception->is_holiday;
        }

        return $this->isWorkingDay($date, $settings);
    }

    /**
     * زودترین تاریخ ممکن ارسال
     *
     * مبدأ = امروز؛ اگر از ساعت cut-off گذشته باشد یا امروز روز کاری نباشد،
     * مبدأ روز کاری بعدی است. سپس lead_days روز اضافه و اولین روز قابل ارسال انتخاب می‌شود.
     */
    public function earliestDate(?CarbonImmutable $now = null): CarbonImmutable
    {
        $settings = $this->settings();
        $now ??= $this->now();
        $today = $now->startOfDay();
        $exceptions = $this->exceptions($today, $today->addDays(self::SCAN_LIMIT));

        $base = $today;
        $pastCutoff = $settings['delivery_cutoff'] !== ''
            && preg_match('/^\d{1,2}:\d{2}$/', $settings['delivery_cutoff'])
            && $now->format('H:i') >= str_pad($settings['delivery_cutoff'], 5, '0', STR_PAD_LEFT);

        if ($pastCutoff || ! $this->isDeliverable($base, $settings, $exceptions)) {
            $base = $base->addDay();
            for ($i = 0; $i < self::SCAN_LIMIT && ! $this->isDeliverable($base, $settings, $exceptions); $i++) {
                $base = $base->addDay();
            }
        }

        $date = $base->addDays($settings['delivery_lead_days']);
        for ($i = 0; $i < self::SCAN_LIMIT && ! $this->isDeliverable($date, $settings, $exceptions); $i++) {
            $date = $date->addDay();
        }

        return $date;
    }

    /**
     * تاریخ‌های قابل انتخاب برای مشتری
     *
     * @return Collection<int, array{date: string, weekday: string, day: string, month: string, label: ?string, cost: int, slot_id: ?int}>
     */
    public function availableDates(?CarbonImmutable $now = null): Collection
    {
        $settings = $this->settings();
        $now ??= $this->now();
        $start = $this->earliestDate($now);
        $exceptions = $this->exceptions($start, $start->addDays(self::SCAN_LIMIT));
        $today = $now->startOfDay();

        $dates = collect();
        $cursor = $start;

        for ($i = 0; $i < self::SCAN_LIMIT && $dates->count() < $settings['delivery_window_days']; $i++, $cursor = $cursor->addDay()) {
            if (! $this->isDeliverable($cursor, $settings, $exceptions)) {
                continue;
            }

            $exception = $exceptions->get($cursor->toDateString());
            $jalali = verta($cursor);
            $diff = (int) $today->diffInDays($cursor);

            $dates->push([
                'date' => $cursor->toDateString(),
                'weekday' => $jalali->format('l'),
                'day' => $jalali->format('j'),
                'month' => $jalali->format('F'),
                'label' => $exception?->label ?: match ($diff) {
                    0 => 'امروز',
                    1 => 'فردا',
                    2 => 'پس‌فردا',
                    default => null,
                },
                // هزینه اختصاصی روز اضافه، در غیر این صورت هزینه پیش‌فرض
                'cost' => ($exception && (int) $exception->cost > 0) ? (int) $exception->cost : (int) $settings['delivery_cost'],
                'slot_id' => $exception?->id,
            ]);
        }

        return $dates;
    }

    /**
     * اعتبارسنجی تاریخ انتخابی (سمت سرور، هنگام ثبت سفارش)
     */
    public function find(?string $date): ?array
    {
        if (! $date) {
            return null;
        }

        return $this->availableDates()->firstWhere('date', $date);
    }
}
