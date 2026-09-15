<?php

namespace App\Enums;

enum MembershipLevel: string
{
    case Regular = 'regular';
    case Silver = 'silver';
    case Gold = 'gold';
    case Platinum = 'platinum';

    public function label(): string
    {
        return match ($this) {
            self::Regular => 'Regular',
            self::Silver => 'Silver',
            self::Gold => 'Gold',
            self::Platinum => 'Platinum',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Platinum => 'indigo',
            self::Gold => 'orange',
            self::Silver => 'blue',
            self::Regular => 'gray',
        };
    }

    public function minSpending(): int
    {
        return match ($this) {
            self::Regular => 0,
            self::Silver => 1_000_000,
            self::Gold => 5_000_000,
            self::Platinum => 15_000_000,
        };
    }
}
