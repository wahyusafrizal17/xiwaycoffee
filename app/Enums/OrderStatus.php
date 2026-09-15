<?php

namespace App\Enums;

enum OrderStatus: string
{
    case Draft = 'draft';
    case Held = 'held';
    case New = 'new';
    case Processing = 'processing';
    case Preparing = 'preparing';
    case Ready = 'ready';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Held => 'Held',
            self::New => 'New',
            self::Processing => 'Processing',
            self::Preparing => 'Preparing',
            self::Ready => 'Ready',
            self::Completed => 'Completed',
            self::Cancelled => 'Cancelled',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft, self::Held => 'gray',
            self::New => 'blue',
            self::Processing, self::Preparing => 'orange',
            self::Ready => 'indigo',
            self::Completed => 'green',
            self::Cancelled => 'red',
        };
    }

    public function isOpen(): bool
    {
        return ! in_array($this, [self::Completed, self::Cancelled], true);
    }
}
