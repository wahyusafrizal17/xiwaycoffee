<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Concerns\AppliesFillableAttribute;

#[Fillable(['production_order_id', 'product_id', 'unit_id', 'quantity_required', 'quantity_used'])]
class ProductionOrderItem extends Model
{
    use AppliesFillableAttribute;

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'quantity_required' => 'decimal:4',
            'quantity_used' => 'decimal:4',
        ];
    }

    public function productionOrder(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }
}
