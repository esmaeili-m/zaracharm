<?php

namespace App\Services\Sms;

/**
 * کدهای خروجی ملی پیامک
 *
 * ارسال (SendByBaseNumber): خروجی موفق recId (عدد بیش از ۱۵ رقم) است و در غیر این صورت کد خطا.
 * کدهای -4، -5 و -10 در مستندات خط خدماتی اشتراکی آمده‌اند؛ بقیه از فهرست عمومی خطاهای وب‌سرویس ملی پیامک.
 * کد ناشناخته «وضعیت نامشخص» ثبت می‌شود و خودکار تکرار نمی‌شود (ممکن است پیامک ارسال شده باشد).
 */
class MelipayamakCodes
{
    /**
     * code => [پیام, دائمی؟, توقف کل عملیات؟]
     * خطای «توقف» به حساب/تنظیمات مربوط است و تکرارش برای گیرندگان بعدی فقط درخواست بی‌فایده می‌سازد.
     */
    public const SEND = [
        0 => ['نام کاربری یا رمز عبور پنل پیامک نادرست است', false, true],
        2 => ['اعتبار پنل پیامک کافی نیست', false, true],
        6 => ['سامانه ملی پیامک در حال به‌روزرسانی است', false, true],
        7 => ['متن پیامک حاوی کلمه فیلترشده است', true, false],
        10 => ['کاربر پنل پیامک فعال نیست', false, true],
        11 => ['پیامک ارسال نشد', false, false],
        12 => ['مدارک کاربر پنل پیامک کامل نیست', false, true],
        16 => ['شماره گیرنده یافت نشد', true, false],
        17 => ['متن پیامک خالی است', true, false],
        35 => ['شماره گیرنده در لیست سیاه مخابرات است', true, false],
        -1 => ['دسترسی به این وب‌سرویس در پنل غیرفعال است', false, true],
        -2 => ['محدودیت تعداد شماره در هر درخواست', true, false],
        -3 => ['خط ارسال در سامانه تعریف نشده است', false, true],
        -4 => ['کد الگو نادرست است یا هنوز توسط ملی پیامک تأیید نشده است', true, true],
        -5 => ['مقادیر ارسالی با متغیرهای الگو همخوانی ندارد', true, false],
        -6 => ['خطای داخلی سامانه ملی پیامک', false, false],
        -7 => ['خطا در شماره فرستنده', false, true],
        -10 => ['در میان مقادیر متغیرها لینک وجود دارد', true, false],
    ];

    /**
     * وضعیت تحویل (GetDeliveries) → [عنوان, وضعیت داخلی]
     * null = وضعیت پیام تغییر نمی‌کند (هنوز نهایی نشده)
     */
    public const DELIVERY = [
        0 => ['ارسال‌شده به مخابرات', null],
        1 => ['رسیده به گوشی', 'delivered'],
        2 => ['نرسیده به گوشی', 'undelivered'],
        3 => ['خطای مخابراتی', 'undelivered'],
        5 => ['خطای نامشخص', 'undelivered'],
        8 => ['رسیده به مخابرات', null],
        16 => ['نرسیده به مخابرات', 'undelivered'],
        35 => ['شماره در لیست سیاه', 'undelivered'],
        100 => ['نامشخص', null],
        -1 => ['شناسه ارسال یافت نشد', null],
    ];

    public static function isRecId(string $value): bool
    {
        return (bool) preg_match('/^\d{16,}$/', trim($value));
    }

    public static function sendResult(string $raw): SendResult
    {
        $raw = trim($raw);

        if (self::isRecId($raw)) {
            return SendResult::accepted($raw);
        }

        if (preg_match('/^-?\d+$/', $raw) && isset(self::SEND[(int) $raw])) {
            [$message, $permanent, $stop] = self::SEND[(int) $raw];

            return SendResult::failed((int) $raw, $message, $permanent, $stop);
        }

        return SendResult::unknown('پاسخ ناشناخته از ملی پیامک: ' . mb_substr($raw, 0, 50), is_numeric($raw) ? (int) $raw : null);
    }

    public static function sendMessage(?int $code): ?string
    {
        return $code === null ? null : (self::SEND[$code][0] ?? 'کد ' . $code);
    }

    public static function deliveryLabel(?int $code): ?string
    {
        return $code === null ? null : (self::DELIVERY[$code][0] ?? 'کد ' . $code);
    }

    public static function deliveryStatus(int $code): ?string
    {
        return self::DELIVERY[$code][1] ?? null;
    }
}
