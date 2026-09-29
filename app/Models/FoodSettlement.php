<?php

namespace App\Models;

use App\Models\Concerns\AppliesFillableAttribute;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['outlet_id', 'user_id', 'settled_on', 'period_from', 'period_to', 'amount', 'notes'])]
class FoodSettlement extends Model
{
    use AppliesFillableAttribute;

    protected function casts(): array
    {
        return [
            'settled_on' => 'date',
            'period_from' => 'date',
            'period_to' => 'date',
            'amount' => 'decimal:2',
        ];
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
