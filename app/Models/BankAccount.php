<?php

namespace App\Models;

use App\Models\Concerns\AppliesFillableAttribute;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['outlet_id', 'name'])]
class BankAccount extends Model
{
    use AppliesFillableAttribute;

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(BankMovement::class);
    }
}
