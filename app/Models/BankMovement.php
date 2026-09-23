<?php

namespace App\Models;

use App\Enums\BankMovementType;
use App\Models\Concerns\AppliesFillableAttribute;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable([
    'bank_account_id', 'outlet_id', 'user_id', 'type', 'amount', 'balance_after',
    'occurred_on', 'reference_type', 'reference_id', 'notes',
])]
class BankMovement extends Model
{
    use AppliesFillableAttribute;

    protected function casts(): array
    {
        return [
            'type' => BankMovementType::class,
            'amount' => 'decimal:2',
            'balance_after' => 'decimal:2',
            'occurred_on' => 'date',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class, 'bank_account_id');
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }
}
