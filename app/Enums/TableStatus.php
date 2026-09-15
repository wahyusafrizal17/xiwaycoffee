<?php

namespace App\Enums;

enum TableStatus: string
{
    case Available = 'available';
    case Occupied = 'occupied';
    case Reserved = 'reserved';

    public function label(): string
    {
        return match ($this) {
            self::Available => 'Available',
            self::Occupied => 'Occupied',
            self::Reserved => 'Reserved',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Available => 'green',
            self::Occupied => 'blue',
            self::Reserved => 'orange',
        };
    }
}
