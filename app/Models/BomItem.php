<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Concerns\AppliesFillableAttribute;

#[Fillable(['bom_id', 'component_id', 'unit_id', 'quantity', 'waste_percentage', 'yield_percentage'])]
class BomItem extends Model
{
    use AppliesFillableAttribute;

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'waste_percentage' => 'decimal:2',
            'yield_percentage' => 'decimal:2',
        ];
    }

    public function bom(): BelongsTo
    {
        return $this->belongsTo(Bom::class);
    }

    public function component(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'component_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function requiredQuantity(float $outputQty): float
    {
        $wasteFactor = 1 + ((float) $this->waste_percentage / 100);
        $yieldFactor = max((float) $this->yield_percentage, 1) / 100;

        return ((float) $this->quantity * $outputQty * $wasteFactor) / $yieldFactor;
    }
}
