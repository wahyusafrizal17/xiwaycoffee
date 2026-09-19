<?php

namespace App\Models;

use App\Models\Concerns\AppliesFillableAttribute;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'capital', 'is_active'])]
class Investor extends Model
{
    use AppliesFillableAttribute;

    protected function casts(): array
    {
        return [
            'capital' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function topups(): HasMany
    {
        return $this->hasMany(InvestorTopup::class);
    }
}
