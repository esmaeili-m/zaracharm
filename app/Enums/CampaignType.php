<?php

namespace App\Enums;

enum CampaignType: int
{
    case Discount = 0;
    case FlashSale = 1;
    case FreeShipping = 2;
    case Gift = 3;
    case BuyXGetY = 4;
}
