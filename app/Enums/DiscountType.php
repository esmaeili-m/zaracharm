<?php

namespace App\Enums;

enum DiscountType:int
{
    /**
     * Percentage discount
     */
    case Percent = 1;

    /**
     * Fixed amount discount
     */
    case Fixed = 2;

    /**
     * Display title
     */
    public function title(): string
    {
        return match ($this) {
            self::Percent => 'درصدی',
            self::Fixed => 'مبلغ ثابت',
        };
    }

    /**
     * English key
     */
    public function key(): string
    {
        return match ($this) {
            self::Percent => 'percent',
            self::Fixed => 'fixed',
        };
    }

    /**
     * Options for select inputs
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case) => [
                $case->value => $case->title(),
            ])
            ->toArray();
    }
}
