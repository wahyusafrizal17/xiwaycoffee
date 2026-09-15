<?php

namespace App\Enums;

enum ExpenseCategory: string
{
    case Ingredients = 'bahan';
    case Rent = 'sewa';
    case Salary = 'gaji';
    case Electricity = 'listrik';
    case Other = 'lain';

    public function label(): string
    {
        return match ($this) {
            self::Ingredients => 'Bahan',
            self::Rent => 'Sewa',
            self::Salary => 'Gaji',
            self::Electricity => 'Listrik',
            self::Other => 'Lainnya',
        };
    }
}
