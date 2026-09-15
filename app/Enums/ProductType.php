<?php

namespace App\Enums;

enum ProductType: string
{
    case Raw = 'raw';
    case SemiFinished = 'semi_finished';
    case Finished = 'finished';
    case Package = 'package';

    public function label(): string
    {
        return match ($this) {
            self::Raw => 'Bahan baku',
            self::SemiFinished => 'Semi jadi',
            self::Finished => 'Menu',
            self::Package => 'Paket',
        };
    }
}
