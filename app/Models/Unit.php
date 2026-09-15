<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Concerns\AppliesFillableAttribute;

#[Fillable(['code', 'name', 'family', 'conversion_factor'])]
class Unit extends Model
{
    use AppliesFillableAttribute, SoftDeletes;

    protected function casts(): array
    {
        return [
            'conversion_factor' => 'decimal:6',
        ];
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function convertTo(self $target, float $quantity): float
    {
        if ($this->id === $target->id) {
            return $quantity;
        }

        if ($this->family && $target->family && $this->family !== $target->family) {
            return $quantity;
        }

        $from = max((float) $this->conversion_factor, 0.000001);
        $to = max((float) $target->conversion_factor, 0.000001);

        return $quantity * $from / $to;
    }
}
