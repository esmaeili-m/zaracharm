<?php

namespace App\Services\Sms;

/**
 * استانداردسازی شماره موبایل ایران به قالب 09xxxxxxxxx
 */
class PhoneNumber
{
    /**
     * @return string|null شماره استاندارد یا null در صورت نامعتبر بودن
     */
    public static function normalize(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        // ارقام فارسی/عربی → لاتین
        $value = strtr($value, [
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        ]);

        $value = preg_replace('/[\s\-\.\(\)\x{200C}\x{200F}\x{200E}]+/u', '', trim($value));

        if ($value === '' || ! preg_match('/^\+?\d+$/', $value)) {
            return null;
        }

        $value = ltrim($value, '+');

        if (str_starts_with($value, '0098')) {
            $value = '0' . substr($value, 4);
        } elseif (str_starts_with($value, '98') && strlen($value) === 12) {
            $value = '0' . substr($value, 2);
        } elseif (str_starts_with($value, '9') && strlen($value) === 10) {
            $value = '0' . $value;
        }

        return preg_match('/^09\d{9}$/', $value) ? $value : null;
    }

    public static function isValid(?string $value): bool
    {
        return self::normalize($value) !== null;
    }

    public static function mask(string $mobile): string
    {
        return strlen($mobile) >= 8 ? substr($mobile, 0, 4) . '***' . substr($mobile, -4) : '***';
    }

    /**
     * تفکیک متن چندخطی (یا جداشده با کاما/فاصله) به شماره‌های معتبر یکتا، تکراری و نامعتبر
     *
     * @return array{valid: list<string>, invalid: list<string>, duplicates: list<string>}
     */
    public static function parseList(string $text): array
    {
        $parts = preg_split('/[\r\n,;،\t]+/u', $text) ?: [];
        $valid = [];
        $invalid = [];
        $duplicates = [];

        foreach ($parts as $raw) {
            $raw = trim($raw);

            if ($raw === '') {
                continue;
            }

            $mobile = self::normalize($raw);

            if ($mobile === null) {
                $invalid[] = $raw;
            } elseif (isset($valid[$mobile])) {
                $duplicates[] = $mobile;
            } else {
                $valid[$mobile] = $mobile;
            }
        }

        return ['valid' => array_values($valid), 'invalid' => $invalid, 'duplicates' => array_values(array_unique($duplicates))];
    }
}
