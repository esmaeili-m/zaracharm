<?php

namespace App\Enums;

enum ProductType:int
{
    case Physical = 1;

    case Digital = 2;

    case Service = 3;

    case GiftCard = 4;

    public function title(): string
    {
        return match ($this) {
            self::Physical => 'فیزیکی',
            self::Digital => 'دیجیتال',
            self::Service => 'خدمات',
            self::GiftCard => 'کارت هدیه',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn ($case) => [
                $case->value => $case->title(),
            ])
            ->toArray();
    }
}
