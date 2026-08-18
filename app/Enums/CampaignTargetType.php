<?php

namespace App\Enums;

enum CampaignTargetType: int
{
    case ALL = 0;
    case PRODUCT = 1;
    case CATEGORY = 2;
    case BRAND = 3;
    case COLLECTION = 4;

    public function label(): string
    {
        return match ($this) {
            self::ALL => 'کل فروشگاه',
            self::PRODUCT => 'محصول',
            self::CATEGORY => 'دسته‌بندی',
            self::BRAND => 'برند',
            self::COLLECTION => 'مجموعه',
        };
    }
}
