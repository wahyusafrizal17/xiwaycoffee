<?php

namespace App\Enums;

enum WasteReason: string
{
    case Damaged = 'damaged';
    case Expired = 'expired';
    case Spoiled = 'spoiled';
    case ProductionWaste = 'production_waste';
    case WrongPreparation = 'wrong_preparation';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Damaged => 'Rusak',
            self::Expired => 'Kadaluarsa',
            self::Spoiled => 'Busuk',
            self::ProductionWaste => 'Sisa produksi',
            self::WrongPreparation => 'Salah olah',
            self::Other => 'Lainnya',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Damaged, self::Spoiled => 'red',
            self::Expired => 'orange',
            self::WrongPreparation => 'indigo',
            self::ProductionWaste, self::Other => 'gray',
        };
    }
}
