<?php

namespace App\Enums;

enum StockMovementType: string
{
    case In = 'in';
    case Out = 'out';
    case Production = 'production';
    case Sale = 'sale';
    case Waste = 'waste';
    case Transfer = 'transfer';
    case Adjustment = 'adjustment';

    public function label(): string
    {
        return match ($this) {
            self::In => 'Masuk',
            self::Out => 'Keluar',
            self::Production => 'Produksi',
            self::Sale => 'Penjualan',
            self::Waste => 'Waste',
            self::Transfer => 'Transfer',
            self::Adjustment => 'Penyesuaian',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::In => 'green',
            self::Out, self::Sale => 'red',
            self::Production => 'blue',
            self::Waste => 'orange',
            self::Transfer => 'gray',
            self::Adjustment => 'indigo',
        };
    }
}
