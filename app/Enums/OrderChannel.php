<?php

namespace App\Enums;

enum OrderChannel: string
{
    case Pos = 'pos';
    case Online = 'online';
    case Pickup = 'pickup';

    public function label(): string
    {
        return match ($this) {
            self::Pos => 'POS',
            self::Online => 'Online',
            self::Pickup => 'Pickup',
        };
    }
}
