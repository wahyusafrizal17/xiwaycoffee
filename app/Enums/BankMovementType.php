<?php

namespace App\Enums;

enum BankMovementType: string
{
    case Qris = 'qris';
    case CashDeposit = 'cash_deposit';
    case Bop = 'bop';
    case FoodSettlement = 'food_settlement';
    case Adjustment = 'adjustment';

    public function label(): string
    {
        return match ($this) {
            self::Qris => 'QRIS',
            self::CashDeposit => 'Setoran kas',
            self::Bop => 'BOP',
            self::FoodSettlement => 'Setoran makanan',
            self::Adjustment => 'Koreksi',
        };
    }

    public function isCredit(): bool
    {
        return match ($this) {
            self::Qris, self::CashDeposit => true,
            self::Bop, self::FoodSettlement => false,
            self::Adjustment => false,
        };
    }
}
