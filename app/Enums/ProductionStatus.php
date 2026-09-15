<?php

namespace App\Enums;

enum ProductionStatus: string
{
    case Draft = 'draft';
    case Planned = 'planned';
    case InProduction = 'in_production';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Planned => 'Direncanakan',
            self::InProduction => 'Diproduksi',
            self::Completed => 'Selesai',
            self::Cancelled => 'Dibatalkan',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Planned => 'blue',
            self::InProduction => 'orange',
            self::Completed => 'green',
            self::Cancelled => 'red',
        };
    }
}
