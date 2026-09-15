<?php

namespace App\Enums;

enum PrinterStation: string
{
    case Cashier = 'cashier';
    case Kitchen = 'kitchen';
    case Bar = 'bar';

    public function label(): string
    {
        return match ($this) {
            self::Cashier => 'Cashier',
            self::Kitchen => 'Kitchen',
            self::Bar => 'Bar',
        };
    }
}
