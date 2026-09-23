<?php

namespace App\Enums;

enum ExpensePaymentMethod: string
{
    case Cash = 'cash';
    case Transfer = 'transfer';
    case Qris = 'qris';
    case Card = 'card';
    case Ewallet = 'ewallet';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Cash',
            self::Transfer => 'Transfer',
            self::Qris => 'QRIS',
            self::Card => 'Debit/Credit',
            self::Ewallet => 'E-Wallet',
        };
    }
}
