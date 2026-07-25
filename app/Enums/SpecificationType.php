<?php

namespace App\Enums;

enum SpecificationType:int
{
    case Text = 1;

    case Number = 2;

    case Boolean = 3;

    case Date = 4;

    case Decimal = 5;
}
