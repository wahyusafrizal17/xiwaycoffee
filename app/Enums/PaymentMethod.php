<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Cash = 'cash';
    case Card = 'card';
    case Qris = 'qris';
    case Transfer = 'transfer';
    case Points = 'points';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Cash',
            self::Card => 'Card',
            self::Qris => 'QRIS',
            self::Transfer => 'Transfer',
            self::Points => 'Loyalty Points',
        };
    }
}
