<?php

namespace App\Services\Sms;

/**
 * متن الگوی ملی پیامک: متغیرها با اندیس از صفر به شکل {0} {1} ... در متن قرار می‌گیرند
 * و مقادیر به همان ترتیب در آرایه text متد SendByBaseNumber ارسال می‌شوند.
 */
class TemplateBody
{
    public const MAX_VALUE_LENGTH = 100;

    /** @return list<int> اندیس‌های یکتای به‌کاررفته (مرتب) */
    public static function indexes(string $body): array
    {
        preg_match_all('/\{(\d+)\}/', $body, $matches);
        $indexes = array_values(array_unique(array_map('intval', $matches[1])));
        sort($indexes);

        return $indexes;
    }

    /** تعداد مقادیری که باید ارسال شود (بزرگ‌ترین اندیس + ۱) */
    public static function count(string $body): int
    {
        $indexes = self::indexes($body);

        return $indexes ? max($indexes) + 1 : 0;
    }

    /**
     * خطاهای ساختاری متن الگو
     *
     * @return list<string>
     */
    public static function errors(string $body): array
    {
        $errors = [];
        $indexes = self::indexes($body);

        if ($indexes && $indexes !== range(0, max($indexes))) {
            $errors[] = 'اندیس متغیرها باید از {0} شروع شود و پشت‌سرهم باشد (مثلاً {0} و {1}).';
        }

        if (preg_match('/\{[^\d}]*\}/u', $body)) {
            $errors[] = 'فقط متغیرهای عددی مثل {0} مجاز است؛ متغیر نام‌دار در ملی پیامک پشتیبانی نمی‌شود.';
        }

        return $errors;
    }

    /** جایگذاری مقادیر برای پیش‌نمایش */
    public static function render(string $body, array $args): string
    {
        return preg_replace_callback('/\{(\d+)\}/', function ($m) use ($args) {
            $value = $args[(int) $m[1]] ?? null;

            return ($value === null || $value === '') ? '[' . $m[0] . ' خالی]' : (string) $value;
        }, $body);
    }

    /**
     * اعتبارسنجی یک مقدار متغیر؛ null یعنی معتبر
     */
    public static function valueError(?string $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return 'مقدار خالی است';
        }

        if (mb_strlen($value) > self::MAX_VALUE_LENGTH) {
            return 'طول مقدار بیش از ' . self::MAX_VALUE_LENGTH . ' نویسه است';
        }

        // ملی پیامک ارسال با لینک در متغیرها را رد می‌کند (کد -10)
        if (preg_match('~(https?://|www\.|\.(ir|com|net|org|me|co)\b)~iu', $value)) {
            return 'لینک در مقدار متغیر مجاز نیست';
        }

        return null;
    }

    /** تعداد تقریبی بخش‌های پیامک (فارسی: ۷۰ نویسه، چندبخشی: ۶۷) */
    public static function parts(string $text): int
    {
        $length = mb_strlen($text);
        $unicode = (bool) preg_match('/[^\x00-\x7F]/', $text);
        [$single, $multi] = $unicode ? [70, 67] : [160, 153];

        return $length <= $single ? 1 : (int) ceil($length / $multi);
    }
}
