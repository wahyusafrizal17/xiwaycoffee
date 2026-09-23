<?php

namespace App\Models;

use App\Enums\ExpenseCategory;
use App\Enums\ExpensePaymentMethod;
use App\Models\Concerns\AppliesFillableAttribute;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['outlet_id', 'user_id', 'spent_on', 'category', 'amount', 'payment_method', 'notes'])]
class OperatingExpense extends Model
{
    use AppliesFillableAttribute;

    protected function casts(): array
    {
        return [
            'spent_on' => 'date',
            'category' => ExpenseCategory::class,
            'payment_method' => ExpensePaymentMethod::class,
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
