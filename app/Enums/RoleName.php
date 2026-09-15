<?php

namespace App\Enums;

enum RoleName: string
{
    case Admin = 'admin';
    case Cashier = 'cashier';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Admin',
            self::Cashier => 'Cashier',
        };
    }
}
