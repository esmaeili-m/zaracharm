<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Morilog\Jalali\Jalalian;

/**
 * تبدیل تاریخ شمسی فرم‌ها (Y/m/d) به Carbon و برعکس
 */
class JalaliDate
{
    public const PATTERN = '/^\d{4}\/\d{1,2}\/\d{1,2}$/';

    public static function toCarbon(?string $value): ?Carbon
    {
        $value = trim((string) $value);

        if (! preg_match(self::PATTERN, self::latinDigits($value))) {
            return null;
        }

        try {
            $carbon = Carbon::instance(Jalalian::fromFormat('Y/m/d', self::latinDigits($value))->toCarbon())->startOfDay();
        } catch (\Throwable) {
            return null;
        }

        return $carbon->year >= 2000 && $carbon->year <= 2100 ? $carbon : null;
    }

    public static function format($date): string
    {
        return $date ? Jalalian::fromCarbon(Carbon::parse($date))->format('Y/m/d') : '';
    }

    public static function today(): string
    {
        return Jalalian::now()->format('Y/m/d');
    }

    public static function latinDigits(string $value): string
    {
        return strtr($value, ['۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9']);
    }
}
