<?php

namespace App\Enums;

enum OrderType: string
{
    case DineIn = 'dine_in';
    case Pickup = 'pickup';
    case Online = 'online';

    public function label(): string
    {
        return match ($this) {
            self::DineIn => 'Makan Disini',
            self::Pickup => 'Bawa Pulang',
            self::Online => 'Online',
        };
    }
}
