<?php

namespace App\Models;

use App\Enums\ExpenseCategory;
use App\Models\Concerns\AppliesFillableAttribute;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['outlet_id', 'user_id', 'spent_on', 'category', 'amount', 'notes'])]
class OperatingExpense extends Model
{
    use AppliesFillableAttribute;

    protected function casts(): array
    {
        return [
            'spent_on' => 'date',
            'category' => ExpenseCategory::class,
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
