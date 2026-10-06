<?php

namespace App\Enums;

/**
 * حالت نمایش تصویر در سکشن‌های صفحه‌ساز (data.pictureMode)
 * مقدارها string هستند چون از قبل به همین شکل در row_sections.data ذخیره شده‌اند.
 */
enum PictureMode: string
{
    case Transparent = 'transparent';
    case Background = 'background';

    public function label(): string
    {
        return match ($this) {
            self::Transparent => 'بدون پس‌زمینه (Transparent)',
            self::Background => 'با پس‌زمینه (Background)',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn ($case) => [$case->value => $case->label()])
            ->toArray();
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    // مقدار ذخیره‌شده را به enum تبدیل می‌کند؛ مقدار نامعتبر/خالی = پیش‌فرض
    public static function resolve(?string $value, self $default = self::Background): self
    {
        return self::tryFrom((string) $value) ?? $default;
    }

    /**
     * حالت تصویر یک سکشن بر اساس data آن.
     * برای سکشن‌هایی که قبل از این تنظیم ذخیره شده‌اند، همان رفتار قبلی Frontend حفظ می‌شود.
     */
    public static function forSection(?string $sectionKey, ?array $data): self
    {
        $data ??= [];

        if ($mode = self::tryFrom((string) ($data['pictureMode'] ?? ''))) {
            return $mode;
        }

        return match ($sectionKey) {
            'campaigns' => self::resolve($data['image_style'] ?? null, self::Transparent),
            'instantOffers' => (int) ($data['view'] ?? 1) === 2 ? self::Transparent : self::Background,
            'products' => (int) ($data['view'] ?? 1) === 3 ? self::Transparent : self::Background,
            // لوگوی برندها معمولاً بدون پس‌زمینه است
            'brands' => self::Transparent,
            default => self::Background,
        };
    }

    public function isTransparent(): bool
    {
        return $this === self::Transparent;
    }
}
