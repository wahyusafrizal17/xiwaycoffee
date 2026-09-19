<?php

namespace App\Models;

use App\Models\Concerns\AppliesFillableAttribute;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['investor_id', 'user_id', 'topped_up_on', 'amount', 'notes'])]
class InvestorTopup extends Model
{
    use AppliesFillableAttribute;

    protected function casts(): array
    {
        return [
            'topped_up_on' => 'date',
            'amount' => 'decimal:2',
        ];
    }

    public function investor(): BelongsTo
    {
        return $this->belongsTo(Investor::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
