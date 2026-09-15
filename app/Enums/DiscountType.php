<?php

namespace App\Enums;

enum DiscountType: string
{
    case Percentage = 'percentage';
    case Nominal = 'nominal';

    public function label(): string
    {
        return match ($this) {
            self::Percentage => 'Percentage',
            self::Nominal => 'Nominal',
        };
    }
}
