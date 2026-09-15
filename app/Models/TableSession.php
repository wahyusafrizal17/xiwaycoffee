<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Concerns\AppliesFillableAttribute;

#[Fillable(['table_id', 'order_id', 'opened_by', 'guest_count', 'opened_at', 'closed_at'])]
class TableSession extends Model
{
    use AppliesFillableAttribute;

    protected function casts(): array
    {
        return [
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function table(): BelongsTo
    {
        return $this->belongsTo(DiningTable::class, 'table_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function opener(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    public function durationMinutes(): int
    {
        $end = $this->closed_at ?? now();

        return (int) $this->opened_at->diffInMinutes($end);
    }
}
