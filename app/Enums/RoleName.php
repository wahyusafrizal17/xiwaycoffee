<?php

namespace App\Enums;

enum RoleName: string
{
    case Admin = 'admin';
    case Cashier = 'cashier';
    case Karyawan = 'karyawan';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Admin',
            self::Cashier => 'Cashier',
            self::Karyawan => 'Karyawan',
        };
    }
}
